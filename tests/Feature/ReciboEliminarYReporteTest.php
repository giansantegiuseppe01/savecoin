<?php

namespace Tests\Feature;

use App\Models\BitacoraAccion;
use App\Models\BitacoraInventario;
use App\Models\DetalleOrden;
use App\Models\Insumo;
use App\Models\Orden;
use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReciboEliminarYReporteTest extends TestCase
{
    use RefreshDatabase;

    private function producto(string $nombre, float $precio, ?Insumo $insumo = null, float $consumo = 1, string $categoria = 'PERROS'): Producto
    {
        $p = Producto::create([
            'nombre' => $nombre, 'codigo' => 'P-'.Str::random(5), 'precio_venta' => $precio,
            'costo_produccion' => 0, 'categoria' => $categoria, 'activo' => true,
        ]);
        if ($insumo) {
            $p->insumos()->attach($insumo->id, ['cantidad_requerida' => $consumo, 'unidad_medida' => 'und']);
        }

        return $p;
    }

    private function insumo(float $stock): Insumo
    {
        return Insumo::create([
            'nombre' => 'Pan', 'codigo' => 'PAN-'.Str::random(4), 'costo_unitario' => 1,
            'stock_actual' => $stock, 'unidad_medida' => 'und', 'stock_minimo' => 0, 'activo' => true,
        ]);
    }

    /** @param array<int,array{0:Producto,1:int}> $lineas */
    private function venta(User $user, array $lineas, array $attrs = []): Orden
    {
        $total = collect($lineas)->sum(fn ($l) => (float) $l[0]->precio_venta * $l[1]);
        // El id se guarda aparte: tras el insert, Eloquent lo reemplaza por el rowid de SQLite
        $id = (string) Str::uuid();
        Orden::forceCreate(array_merge([
            'id' => $id,
            'numero_recibo' => 'ORD-'.Str::random(6),
            'user_id' => $user->id,
            'cliente_nombre' => 'General',
            'tipo' => 'venta',
            'estado' => 'cerrada',
            'metodo_pago' => 'efectivo',
            'subtotal' => $total,
            'total' => $total,
            'tasa_cambio' => 100,
            'fecha_hora' => now(),
            'is_local' => false,
        ], $attrs));

        foreach ($lineas as [$producto, $cantidad]) {
            DetalleOrden::create([
                'orden_id' => $id,
                'producto_id' => $producto->id,
                'producto_nombre' => $producto->nombre,
                'cantidad' => $cantidad,
                'precio_unitario' => $producto->precio_venta,
                'subtotal' => $producto->precio_venta * $cantidad,
                'total' => $producto->precio_venta * $cantidad,
            ]);
        }

        return Orden::whereKey($id)->firstOrFail();
    }

    public function test_solo_el_admin_elimina_recibos_y_exige_motivo(): void
    {
        $empleado = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $orden = $this->venta($empleado, [[$this->producto('Perro', 2), 1]]);

        $this->actingAs($empleado)->delete("/recibos/{$orden->id}", ['motivo' => 'x prueba'])->assertStatus(403);
        $this->assertNotNull(Orden::find($orden->id));

        $this->actingAs($admin)->delete("/recibos/{$orden->id}", [])->assertSessionHasErrors('motivo');
        $this->assertNotNull(Orden::find($orden->id));
    }

    public function test_eliminar_un_recibo_devuelve_inventario_y_queda_en_la_bitacora(): void
    {
        $admin = User::factory()->admin()->create();
        $pan = $this->insumo(100);
        $perro = $this->producto('Perro', 2, $pan, 2);

        // Venta real: descuenta 2 por perro × 3 = 6, sin pasar por la bitácora de inventario
        $advertencias = [];
        app(InventarioService::class)->descontarVenta($perro, 3, null, 'ORD-1', $admin->id, $advertencias);
        $this->assertEquals(94.0, (float) $pan->fresh()->stock_actual);
        $this->assertSame(0, BitacoraInventario::count());

        $orden = $this->venta($admin, [[$perro, 3]], ['numero_recibo' => 'ORD-1']);

        $this->actingAs($admin)->delete("/recibos/{$orden->id}", ['motivo' => 'Venta duplicada'])
            ->assertRedirect(route('recibos.index'))->assertSessionHas('success');

        $this->assertNull(Orden::find($orden->id));
        $this->assertNotNull(Orden::withTrashed()->find($orden->id));
        $this->assertEquals(100.0, (float) $pan->fresh()->stock_actual);

        $accion = BitacoraAccion::firstOrFail();
        $this->assertSame('recibo_eliminado', $accion->accion);
        $this->assertSame('Venta duplicada', $accion->motivo);
        $this->assertSame($admin->id, $accion->user_id);
        $this->assertSame('ORD-1', $accion->datos['numero_recibo']);
        $this->assertSame('Perro', $accion->datos['productos'][0]['producto']);
        $this->assertSame(0, BitacoraInventario::count()); // no ensucia la bitácora de inventario
    }

    public function test_eliminar_un_recibo_anulado_no_devuelve_stock_otra_vez(): void
    {
        $admin = User::factory()->admin()->create();
        $pan = $this->insumo(50);
        $orden = $this->venta($admin, [[$this->producto('Perro', 2, $pan), 1]], ['estado' => 'anulada']);

        $this->actingAs($admin)->delete("/recibos/{$orden->id}", ['motivo' => 'Limpieza'])->assertRedirect();

        $this->assertEquals(50.0, (float) $pan->fresh()->stock_actual);
        $this->assertFalse(BitacoraAccion::firstOrFail()->datos['stock_repuesto']);
    }

    public function test_eliminar_recalcula_un_turno_cerrado(): void
    {
        $admin = User::factory()->admin()->create();
        $turno = Turno::create([
            'codigo' => 'T-'.Str::random(5), 'user_id' => $admin->id, 'usuario_nombre' => 'Admin',
            'fecha' => now()->toDateString(), 'hora_apertura' => now()->subHour(), 'hora_cierre' => now(),
            'estado' => 'cerrado', 'monto_apertura' => 0, 'total_ventas' => 12, 'numero_ordenes' => 2,
        ]);
        $perro = $this->producto('Perro', 2);
        $a = $this->venta($admin, [[$perro, 1]], ['turno_id' => $turno->id]);
        $this->venta($admin, [[$this->producto('Combo', 10), 1]], ['turno_id' => $turno->id]);

        $this->actingAs($admin)->delete("/recibos/{$a->id}", ['motivo' => 'Error de cobro'])->assertRedirect();

        $turno->refresh();
        $this->assertEquals(10.0, (float) $turno->total_ventas);
        $this->assertSame(1, (int) $turno->numero_ordenes);
    }

    public function test_los_reembolsos_no_se_eliminan(): void
    {
        $admin = User::factory()->admin()->create();
        $orden = $this->venta($admin, [[$this->producto('Perro', 2), 1]], ['tipo' => 'reembolso', 'estado' => 'completada']);

        $this->actingAs($admin)->delete("/recibos/{$orden->id}", ['motivo' => 'prueba'])->assertSessionHas('error');
        $this->assertNotNull(Orden::find($orden->id));
    }

    public function test_la_bitacora_de_inventario_solo_muestra_movimientos_manuales(): void
    {
        $admin = User::factory()->admin()->create();
        $pan = $this->insumo(10);
        $vieja = $this->venta($admin, [[$this->producto('Perro', 2), 1]], ['numero_recibo' => 'ORD-VIEJA']);

        BitacoraInventario::create([
            'insumo_id' => $pan->id, 'orden_id' => $vieja->id, 'tipo_movimiento' => 'salida',
            'cantidad_anterior' => 10, 'cantidad_movimiento' => 1, 'cantidad_nueva' => 9,
            'motivo' => 'Venta en ORD-VIEJA', 'user_id' => $admin->id,
        ]);
        app(InventarioService::class)->registrarMovimiento($pan, 'entrada', 5, 'Compra del lunes', $admin->id);

        $this->actingAs($admin)->get('/inventario/bitacora')
            ->assertOk()->assertSee('Compra del lunes')->assertDontSee('Venta en ORD-VIEJA');
    }

    public function test_el_reporte_suma_unidades_por_producto_y_excluye_anuladas_eliminadas_y_reembolsos(): void
    {
        $admin = User::factory()->admin()->create();
        $perro = $this->producto('Perro caliente', 2);
        $refresco = $this->producto('Refresco', 1, null, 1, 'BEBIDAS');

        $this->venta($admin, [[$perro, 3], [$refresco, 1]]);
        $this->venta($admin, [[$perro, 2]]);
        $this->venta($admin, [[$perro, 9]], ['estado' => 'anulada']);
        $this->venta($admin, [[$perro, 9]], ['tipo' => 'reembolso', 'estado' => 'completada']);
        $this->venta($admin, [[$perro, 4]], ['fecha_hora' => now()->subDays(3)]); // otro día
        $eliminada = $this->venta($admin, [[$perro, 7]]);
        $eliminada->delete();

        $hoy = now()->toDateString();
        $resp = $this->actingAs($admin)->get("/reportes/ventas-productos?fecha_inicio={$hoy}&fecha_fin={$hoy}");
        $resp->assertOk();

        $filas = $resp->viewData('filas');
        $this->assertCount(2, $filas);
        $this->assertSame('Perro caliente', $filas[0]->producto);
        $this->assertEquals(5.0, $filas[0]->unidades);
        $this->assertEquals(10.0, $filas[0]->ingresos);
        $this->assertEquals(1.0, $filas[1]->unidades);
        $this->assertEquals(6.0, $resp->viewData('totalUnidades'));
        $this->assertSame('Perro caliente', $resp->viewData('masVendido')->producto);

        // Filtro por producto y por categoría
        $r2 = $this->actingAs($admin)->get("/reportes/ventas-productos?fecha_inicio={$hoy}&fecha_fin={$hoy}&producto_id={$refresco->id}");
        $this->assertCount(1, $r2->viewData('filas'));
        $r3 = $this->actingAs($admin)->get("/reportes/ventas-productos?fecha_inicio={$hoy}&fecha_fin={$hoy}&categoria=BEBIDAS");
        $this->assertSame('Refresco', $r3->viewData('filas')[0]->producto);

        // Rango de varios días, visto día por día
        $desde = now()->subDays(3)->toDateString();
        $r4 = $this->actingAs($admin)->get("/reportes/ventas-productos?fecha_inicio={$desde}&fecha_fin={$hoy}&agrupar=dia");
        $r4->assertOk();
        $this->assertCount(3, $r4->viewData('filas')); // hoy: perro y refresco; hace 3 días: perro
        $this->assertEquals(9.0, $r4->viewData('totalUnidades'));
    }

    public function test_el_empleado_no_ve_el_reporte(): void
    {
        $this->actingAs(User::factory()->create())->get('/reportes/ventas-productos')->assertForbidden();
    }
}
