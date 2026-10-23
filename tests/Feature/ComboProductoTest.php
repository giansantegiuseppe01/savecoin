<?php

namespace Tests\Feature;

use App\Models\Insumo;
use App\Models\Orden;
use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComboProductoTest extends TestCase
{
    use RefreshDatabase;

    private function insumo(string $nombre, float $stock, float $costo = 1): Insumo
    {
        return Insumo::create([
            'nombre' => $nombre,
            'codigo' => 'INS-'.Str::random(5),
            'costo_unitario' => $costo,
            'stock_actual' => $stock,
            'unidad_medida' => 'und',
            'stock_minimo' => 0,
        ]);
    }

    private function producto(string $nombre, float $precio, array $insumos = []): Producto
    {
        $producto = Producto::create([
            'nombre' => $nombre,
            'codigo' => 'PRD-'.Str::random(5),
            'precio_venta' => $precio,
        ]);

        foreach ($insumos as [$insumo, $cantidad]) {
            $producto->insumos()->attach($insumo->id, ['cantidad_requerida' => $cantidad, 'unidad_medida' => 'und']);
        }

        return $producto;
    }

    private function turnoAbierto(User $user): void
    {
        Turno::create([
            'codigo' => 'T-'.Str::random(6),
            'user_id' => $user->id,
            'usuario_nombre' => 'Empleado',
            'fecha' => now()->toDateString(),
            'hora_apertura' => now(),
            'estado' => 'abierto',
            'monto_apertura' => 0,
        ]);
    }

    public function test_el_consumo_de_un_combo_incluye_los_insumos_de_sus_productos(): void
    {
        $pan = $this->insumo('Pan', 100);
        $salchicha = $this->insumo('Salchicha', 100);
        $botella = $this->insumo('Botella refresco', 100);

        $perro = $this->producto('Perro caliente', 3, [[$pan, 1], [$salchicha, 1]]);
        $refresco = $this->producto('Refresco botella', 1, [[$botella, 1]]);

        $combo = $this->producto('Combo Resuelve', 6);
        $combo->componentes()->attach($perro->id, ['cantidad' => 2]);
        $combo->componentes()->attach($refresco->id, ['cantidad' => 1]);

        $consumo = $combo->fresh()->consumoInsumos(3);

        $this->assertEquals(6.0, $consumo[$pan->id]);
        $this->assertEquals(6.0, $consumo[$salchicha->id]);
        $this->assertEquals(3.0, $consumo[$botella->id]);
        // costo: 2 perros (2 insumos de 1) + 1 refresco (1) = 5
        $this->assertEquals(5.0, $combo->fresh()->costoCalculado());
    }

    public function test_un_combo_puede_incluir_otro_combo(): void
    {
        $pan = $this->insumo('Pan', 100);
        $perro = $this->producto('Perro', 3, [[$pan, 1]]);

        $dobles = $this->producto('Dos perros', 5);
        $dobles->componentes()->attach($perro->id, ['cantidad' => 2]);

        $familiar = $this->producto('Familiar', 10);
        $familiar->componentes()->attach($dobles->id, ['cantidad' => 2]);

        $this->assertEquals([$pan->id => 8.0], $familiar->fresh()->consumoInsumos(2));
    }

    public function test_vender_un_combo_descuenta_el_inventario_de_cada_producto(): void
    {
        $empleado = User::factory()->create();
        $this->turnoAbierto($empleado);

        $pan = $this->insumo('Pan', 20);
        $botella = $this->insumo('Botella', 10);
        $perro = $this->producto('Perro', 3, [[$pan, 1]]);
        $refresco = $this->producto('Refresco', 1, [[$botella, 1]]);
        $combo = $this->producto('Combo Resuelve', 6);
        $combo->componentes()->attach($perro->id, ['cantidad' => 2]);
        $combo->componentes()->attach($refresco->id, ['cantidad' => 1]);

        $id = (string) Str::uuid();
        Orden::forceCreate([
            'id' => $id,
            'numero_recibo' => 'ORD-COMBO-1',
            'user_id' => $empleado->id,
            'cliente_nombre' => 'General',
            'tipo' => 'venta',
            'estado' => 'abierta',
            'subtotal' => 0,
            'total' => 0,
            'tasa_cambio' => 1,
            'fecha_hora' => now(),
            'is_local' => false,
        ]);

        $this->actingAs($empleado, 'sanctum')
            ->putJson("/api/pos/ordenes/{$id}", [
                'estado' => 'cerrada',
                'metodo_pago' => 'efectivo',
                'subtotal' => 18,
                'total' => 18,
                'is_local' => false,
                'detalles' => [[
                    'producto_id' => $combo->id,
                    'producto_nombre' => $combo->nombre,
                    'precio_unitario' => 6,
                    'cantidad' => 3,
                ]],
            ])
            ->assertOk();

        // 3 combos = 6 perros (6 panes) + 3 refrescos (3 botellas)
        $this->assertEquals(14.0, (float) $pan->fresh()->stock_actual);
        $this->assertEquals(7.0, (float) $botella->fresh()->stock_actual);
        // costo de insumos = 6 + 3
        $this->assertEquals(9.0, (float) Orden::find($id)->costo_insumos);
    }

    public function test_el_catalogo_de_la_app_incluye_los_componentes_del_combo(): void
    {
        $empleado = User::factory()->create();
        $perro = $this->producto('Perro', 3);
        $combo = $this->producto('Combo', 6);
        $combo->componentes()->attach($perro->id, ['cantidad' => 2]);

        $data = $this->actingAs($empleado, 'sanctum')->getJson('/api/pos/productos')->assertOk()->json();

        $fila = collect($data)->firstWhere('id', $combo->id);
        $this->assertCount(1, $fila['componentes']);
        $this->assertEquals(2, (float) $fila['componentes'][0]['pivot']['cantidad']);
    }

    public function test_crear_y_editar_combos_desde_la_web_y_rechazar_referencias_circulares(): void
    {
        $admin = User::factory()->admin()->create();
        $pan = $this->insumo('Pan', 100, 2);
        $perro = $this->producto('Perro', 3, [[$pan, 1]]);

        $this->actingAs($admin)->postJson('/productos', [
            'nombre' => 'Combo Resuelve',
            'codigo' => 'COMBO-1',
            'precio_venta' => 6,
            'es_combo' => '1',
            'componentes' => [['producto_id' => $perro->id, 'cantidad' => 2]],
        ])->assertOk()->assertJson(['success' => true]);

        $combo = Producto::where('codigo', 'COMBO-1')->firstOrFail();
        $this->assertCount(1, $combo->componentes);
        $this->assertEquals(4.0, (float) $combo->costo_produccion); // 2 perros x 1 pan x costo 2

        // El perro no puede incluir al combo que ya lo contiene
        $this->actingAs($admin)->putJson("/productos/{$perro->id}", [
            'nombre' => 'Perro',
            'codigo' => $perro->codigo,
            'precio_venta' => 3,
            'es_combo' => '1',
            'componentes' => [['producto_id' => $combo->id, 'cantidad' => 1]],
        ])->assertStatus(422);

        // Ni incluirse a sí mismo
        $this->actingAs($admin)->putJson("/productos/{$perro->id}", [
            'nombre' => 'Perro',
            'codigo' => $perro->codigo,
            'precio_venta' => 3,
            'es_combo' => '1',
            'componentes' => [['producto_id' => $perro->id, 'cantidad' => 1]],
        ])->assertStatus(422);

        // Un producto que forma parte de un combo no se puede eliminar
        $this->actingAs($admin)->deleteJson("/productos/{$perro->id}")->assertStatus(422);
    }

    public function test_un_combo_vendido_sin_conexion_descuenta_inventario_al_sincronizar_una_sola_vez(): void
    {
        $empleado = User::factory()->create();
        $pan = $this->insumo('Pan', 20);
        $botella = $this->insumo('Botella', 10);
        $perro = $this->producto('Perro', 3, [[$pan, 1]]);
        $refresco = $this->producto('Refresco', 1, [[$botella, 1]]);
        $combo = $this->producto('Combo Resuelve', 6);
        $combo->componentes()->attach($perro->id, ['cantidad' => 2]);
        $combo->componentes()->attach($refresco->id, ['cantidad' => 1]);

        $payload = ['ordenes' => [[
            'id' => (string) Str::uuid(),
            'numero_recibo' => 'LOC-COMBO-1',
            'cliente_nombre' => 'General',
            'tipo' => 'venta',
            'subtotal' => 12,
            'descuento' => 0,
            'impuesto' => 0,
            'total' => 12,
            'tasa_cambio' => 36.5,
            'aplica_iva' => false,
            'estado' => 'cerrada',
            'metodo_pago' => 'efectivo',
            'fecha_hora' => now()->toIso8601String(),
            'detalles' => [[
                'producto_id' => $combo->id,
                'producto_nombre' => 'Combo Resuelve',
                'precio_unitario' => 6,
                'cantidad' => 2,
                'subtotal' => 12,
            ]],
        ]]];

        $this->actingAs($empleado, 'sanctum')->postJson('/api/sync/ordenes', $payload)->assertOk();
        // Reenviar la misma orden no descuenta otra vez
        $this->actingAs($empleado, 'sanctum')->postJson('/api/sync/ordenes', $payload)->assertOk();

        // 2 combos = 4 perros (4 panes) + 2 refrescos (2 botellas)
        $this->assertEquals(16.0, (float) $pan->fresh()->stock_actual);
        $this->assertEquals(8.0, (float) $botella->fresh()->stock_actual);
        $this->assertEquals(6.0, (float) Orden::where('numero_recibo', 'LOC-COMBO-1')->value('costo_insumos'));
    }
}
