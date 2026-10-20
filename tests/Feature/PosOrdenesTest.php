<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Insumo;
use App\Models\Orden;
use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosOrdenesTest extends TestCase
{
    use RefreshDatabase;

    private function turnoAbierto(User $user): Turno
    {
        return Turno::create([
            'codigo' => 'T-'.Str::random(6),
            'user_id' => $user->id,
            'usuario_nombre' => 'Empleado',
            'fecha' => now()->toDateString(),
            'hora_apertura' => now(),
            'estado' => 'abierto',
            'monto_apertura' => 0,
        ]);
    }

    /** @return array{0: Producto, 1: Insumo} */
    private function productoConInsumo(float $stock, float $requerido): array
    {
        $insumo = Insumo::create([
            'nombre' => 'Harina',
            'codigo' => 'INS-'.Str::random(5),
            'costo_unitario' => 1,
            'stock_actual' => $stock,
            'unidad_medida' => 'kg',
            'stock_minimo' => 0,
        ]);

        $producto = Producto::create([
            'nombre' => 'Pan',
            'codigo' => 'PRD-'.Str::random(5),
            'precio_venta' => 5,
        ]);

        $producto->insumos()->attach($insumo->id, [
            'cantidad_requerida' => $requerido,
            'unidad_medida' => 'kg',
        ]);

        return [$producto, $insumo];
    }

    public function test_cerrar_una_orden_creada_con_conexion_descuenta_stock_una_sola_vez(): void
    {
        $empleado = User::factory()->create();
        $this->turnoAbierto($empleado);
        [$producto, $insumo] = $this->productoConInsumo(10, 2);

        // Orden abierta creada por la app con conexión (is_local = false)
        $id = (string) Str::uuid();
        Orden::forceCreate([
            'id' => $id,
            'numero_recibo' => 'ORD-ONLINE-1',
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

        $payload = [
            'estado' => 'cerrada',
            'metodo_pago' => 'efectivo',
            'subtotal' => 15,
            'total' => 15,
            'is_local' => false,
            'detalles' => [[
                'producto_id' => $producto->id,
                'producto_nombre' => $producto->nombre,
                'precio_unitario' => 5,
                'cantidad' => 3,
            ]],
        ];

        $this->actingAs($empleado, 'sanctum')
            ->putJson("/api/pos/ordenes/{$id}", $payload)
            ->assertOk();

        // 10 - (2 por unidad x 3 unidades)
        $this->assertEquals(4.0, (float) $insumo->fresh()->stock_actual);

        // Reintentar el cierre no debe descontar otra vez
        $this->actingAs($empleado, 'sanctum')
            ->putJson("/api/pos/ordenes/{$id}", $payload)
            ->assertStatus(400);

        $this->assertEquals(4.0, (float) $insumo->fresh()->stock_actual);
    }

    public function test_actualizar_una_orden_abierta_no_descuenta_stock(): void
    {
        $empleado = User::factory()->create();
        $this->turnoAbierto($empleado);
        [$producto, $insumo] = $this->productoConInsumo(10, 2);

        $id = (string) Str::uuid();
        Orden::forceCreate([
            'id' => $id,
            'numero_recibo' => 'ORD-ONLINE-2',
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
                'estado' => 'abierta',
                'detalles' => [[
                    'producto_id' => $producto->id,
                    'producto_nombre' => $producto->nombre,
                    'precio_unitario' => 5,
                    'cantidad' => 1,
                ]],
            ])
            ->assertOk();

        $this->assertEquals(10.0, (float) $insumo->fresh()->stock_actual);
    }

    public function test_la_respuesta_devuelve_ids_de_detalle_distintos_y_como_texto(): void
    {
        $empleado = User::factory()->create();
        $this->turnoAbierto($empleado);
        [$producto] = $this->productoConInsumo(10, 1);

        $id = (string) Str::uuid();
        Orden::forceCreate([
            'id' => $id,
            'numero_recibo' => 'ORD-ONLINE-3',
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

        $linea = fn (int $cantidad) => [
            'producto_id' => $producto->id,
            'producto_nombre' => $producto->nombre,
            'precio_unitario' => 5,
            'cantidad' => $cantidad,
        ];

        $response = $this->actingAs($empleado, 'sanctum')
            ->putJson("/api/pos/ordenes/{$id}", [
                'estado' => 'abierta',
                'detalles' => [$linea(1), $linea(2), $linea(3)],
            ])
            ->assertOk();

        $ids = array_column($response->json('data.detalles'), 'id');
        $this->assertCount(3, $ids);
        $this->assertCount(3, array_unique($ids), 'Cada detalle debe tener un id distinto');
        foreach ($ids as $detalleId) {
            $this->assertIsString($detalleId);
            $this->assertTrue(Str::isUuid($detalleId));
        }
    }

    public function test_sync_usa_el_cliente_existente_y_no_crea_duplicados(): void
    {
        $empleado = User::factory()->create();
        $cliente = Cliente::create([
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'cedula' => 'V12345678',
        ]);

        [$producto] = $this->productoConInsumo(10, 1);

        $response = $this->actingAs($empleado, 'sanctum')->postJson('/api/sync/ordenes', [
            'ordenes' => [[
                'id' => (string) Str::uuid(),
                'numero_recibo' => 'LOC-1',
                'cliente_id' => (string) $cliente->id,
                'cliente_nombre' => 'Ana Pérez',
                'tipo' => 'venta',
                'subtotal' => 10,
                'descuento' => 0,
                'impuesto' => 0,
                'total' => 10,
                'tasa_cambio' => 36.5,
                'aplica_iva' => false,
                'estado' => 'cerrada',
                'metodo_pago' => 'efectivo',
                'fecha_hora' => now()->toIso8601String(),
                'is_local' => true, // las órdenes pendientes siempre viajan así
                'detalles' => [[
                    'producto_id' => $producto->id,
                    'producto_nombre' => 'Pan',
                    'precio_unitario' => 10,
                    'cantidad' => 1,
                    'subtotal' => 10,
                ]],
            ]],
        ]);

        $response->assertOk();
        $this->assertSame(1, Cliente::count(), 'No debe crearse un cliente nuevo');
        $this->assertEquals($cliente->id, Orden::where('numero_recibo', 'LOC-1')->value('cliente_id'));
    }
}
