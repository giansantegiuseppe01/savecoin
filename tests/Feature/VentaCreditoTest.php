<?php

namespace Tests\Feature;

use App\Models\AbonoCredito;
use App\Models\Cliente;
use App\Models\Insumo;
use App\Models\Orden;
use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use App\Models\VentaCredito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Str;
use Tests\TestCase;

class VentaCreditoTest extends TestCase
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

    private function cliente(): Cliente
    {
        return Cliente::create(['nombre' => 'Ana', 'apellido' => 'Pérez', 'cedula' => 'V-'.Str::random(6)]);
    }

    private function ventaCredito(User $empleado, Producto $producto, Cliente $cliente, float $total = 15): TestResponse
    {
        return tap($this->actingAs($empleado, 'sanctum')->postJson('/api/pos/ordenes', [
            'estado' => 'cerrada',
            'metodo_pago' => 'credito',
            'cliente_id' => $cliente->id,
            'cliente_nombre' => $cliente->nombre_completo,
            'subtotal' => $total,
            'total' => $total,
            'detalles' => [[
                'producto_id' => $producto->id,
                'cantidad' => 3,
                'precio_unitario' => $total / 3,
            ]],
        ]), fn ($r) => $r->assertCreated());
    }

    public function test_credito_descuenta_inventario_y_queda_como_deuda_del_cliente(): void
    {
        $empleado = User::factory()->create();
        $turno = $this->turnoAbierto($empleado);
        [$producto, $insumo] = $this->productoConInsumo(10, 2);
        $cliente = $this->cliente();

        $respuesta = $this->ventaCredito($empleado, $producto, $cliente);
        $orden = Orden::find($respuesta->json('data.id'));

        // El inventario se descontó y el ticket no tiene método de pago en caja
        $this->assertEquals(4.0, (float) $insumo->fresh()->stock_actual);
        $this->assertNull($orden->metodo_pago);

        // La deuda queda a nombre del cliente
        $this->assertDatabaseHas('ventas_credito', ['orden_id' => $orden->id, 'cliente_id' => $cliente->id, 'saldo' => 15, 'estado' => 'pendiente']);
        $this->assertEquals(15.0, (float) $cliente->fresh()->deuda);

        // Mientras no se cobre, no entra a la caja del turno
        $this->assertEquals(0.0, (float) $turno->fresh()->resumenCierre()['total_usd']);
    }

    public function test_credito_exige_cliente(): void
    {
        $empleado = User::factory()->create();
        $this->turnoAbierto($empleado);
        [$producto] = $this->productoConInsumo(10, 2);

        $this->actingAs($empleado, 'sanctum')->postJson('/api/pos/ordenes', [
            'estado' => 'cerrada',
            'metodo_pago' => 'credito',
            'subtotal' => 5,
            'total' => 5,
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => 1, 'precio_unitario' => 5]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('ventas_credito', 0);
    }

    public function test_cobro_se_aplica_a_las_ventas_mas_antiguas_y_entra_a_caja(): void
    {
        $empleado = User::factory()->create();
        $this->turnoAbierto($empleado);
        $admin = User::factory()->admin()->create();
        $turno = $this->turnoAbierto($admin);
        [$producto] = $this->productoConInsumo(100, 1);
        $cliente = $this->cliente();

        $this->ventaCredito($empleado, $producto, $cliente, 15);
        $this->ventaCredito($empleado, $producto, $cliente, 9);

        // Cobro parcial de 20: 15 del primer ticket y 5 del segundo
        $this->actingAs($admin)
            ->postJson("/clientes/{$cliente->id}/abonos", ['monto' => 20, 'forma_pago' => 'efectivo'])
            ->assertOk();

        $ventas = VentaCredito::orderBy('id')->get();
        $this->assertEquals('pagada', $ventas[0]->estado);
        $this->assertEquals(0.0, (float) $ventas[0]->saldo);
        $this->assertEquals(4.0, (float) $ventas[1]->saldo);
        $this->assertEquals(4.0, (float) $cliente->fresh()->deuda);
        $this->assertEquals(2, AbonoCredito::count());

        // El cobro entra a la caja del turno con su forma de pago
        $resumen = $turno->fresh()->resumenCierre();
        $efectivo = collect($resumen['metodos'])->firstWhere('metodo', 'efectivo');
        $this->assertEquals(20.0, (float) $efectivo['total_usd']);
    }

    public function test_no_permite_cobrar_mas_de_lo_que_debe(): void
    {
        $empleado = User::factory()->create();
        $this->turnoAbierto($empleado);
        $admin = User::factory()->admin()->create();
        $this->turnoAbierto($admin);
        [$producto] = $this->productoConInsumo(100, 1);
        $cliente = $this->cliente();
        $this->ventaCredito($empleado, $producto, $cliente, 15);

        $this->actingAs($admin)
            ->postJson("/clientes/{$cliente->id}/abonos", ['monto' => 16, 'forma_pago' => 'efectivo'])
            ->assertStatus(422);

        $this->assertEquals(15.0, (float) $cliente->fresh()->deuda);
        $this->assertEquals(0, AbonoCredito::count());
    }

    public function test_ticket_anulado_no_cuenta_como_deuda(): void
    {
        $empleado = User::factory()->create();
        $this->turnoAbierto($empleado);
        [$producto] = $this->productoConInsumo(100, 1);
        $cliente = $this->cliente();
        $orden = $this->ventaCredito($empleado, $producto, $cliente, 15);

        Orden::whereKey($orden->json('data.id'))->update(['estado' => 'anulada']);

        $this->assertEquals(0.0, (float) $cliente->fresh()->deuda);
    }
}
