<?php

namespace Tests\Feature;

use App\Models\CuentaPorPagar;
use App\Models\Configuracion;
use App\Models\LibroContable;
use App\Models\PagoPorPagar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuentasPorPagarTest extends TestCase
{
    use RefreshDatabase;

    /** Deja saldo en el libro contable (en Bs) para poder hacer pagos. */
    private function conSaldoEnLibro(float $bs = 10000): void
    {
        Configuracion::create(['tasa_cambio' => 1]);
        LibroContable::create([
            'fecha' => now()->toDateString(), 'tipo_movimiento' => 'entrada', 'monto' => $bs,
            'descripcion' => 'Saldo inicial', 'saldo_despues' => $bs,
        ]);
    }

    private function crearCuenta(User $admin, float $monto = 100): CuentaPorPagar
    {
        $this->actingAs($admin)->post('/cuentas-por-pagar', [
            'acreedor' => 'Distribuidora Pérez',
            'tipo' => 'factura',
            'concepto' => 'Harina',
            'numero_documento' => 'F-001',
            'monto' => $monto,
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->addDays(10)->toDateString(),
        ])->assertRedirect();

        return CuentaPorPagar::latest('id')->firstOrFail();
    }

    public function test_admin_registra_una_factura_con_saldo_completo(): void
    {
        $admin = User::factory()->admin()->create();

        $cuenta = $this->crearCuenta($admin, 100);

        $this->assertEquals(100.0, (float) $cuenta->saldo);
        $this->assertEquals('pendiente', $cuenta->estado);
        $this->assertEquals(100.0, (float) CuentaPorPagar::pendientes()->sum('saldo'));
    }

    public function test_pagos_parciales_y_pago_total_marcan_la_cuenta_como_pagada(): void
    {
        $admin = User::factory()->admin()->create();
        $this->conSaldoEnLibro();
        $cuenta = $this->crearCuenta($admin, 100);

        $this->actingAs($admin)->post("/cuentas-por-pagar/{$cuenta->id}/pagos", [
            'monto' => 40, 'forma_pago' => 'transferencia', 'fecha' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertEquals(60.0, (float) $cuenta->fresh()->saldo);
        $this->assertEquals('pendiente', $cuenta->fresh()->estado);

        $this->actingAs($admin)->post("/cuentas-por-pagar/{$cuenta->id}/pagos", [
            'monto' => 60, 'forma_pago' => 'efectivo', 'fecha' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertEquals(0.0, (float) $cuenta->fresh()->saldo);
        $this->assertEquals('pagada', $cuenta->fresh()->estado);
        $this->assertEquals(2, PagoPorPagar::count());
    }

    public function test_no_permite_pagar_mas_de_lo_que_se_debe(): void
    {
        $admin = User::factory()->admin()->create();
        $cuenta = $this->crearCuenta($admin, 100);

        $this->actingAs($admin)->post("/cuentas-por-pagar/{$cuenta->id}/pagos", [
            'monto' => 150, 'forma_pago' => 'efectivo', 'fecha' => now()->toDateString(),
        ])->assertSessionHasErrors('pago');

        $this->assertEquals(100.0, (float) $cuenta->fresh()->saldo);
        $this->assertEquals(0, PagoPorPagar::count());
    }

    public function test_cada_pago_sale_del_libro_contable_como_salida_en_bolivares(): void
    {
        $admin = User::factory()->admin()->create();
        Configuracion::create(['tasa_cambio' => 50]);
        LibroContable::create([
            'fecha' => now()->toDateString(), 'tipo_movimiento' => 'entrada', 'monto' => 10000,
            'descripcion' => 'Saldo inicial', 'saldo_despues' => 10000,
        ]);
        $cuenta = $this->crearCuenta($admin, 100);

        $this->actingAs($admin)->post("/cuentas-por-pagar/{$cuenta->id}/pagos", [
            'monto' => 40, 'forma_pago' => 'transferencia', 'referencia' => 'REF9', 'fecha' => now()->toDateString(),
        ])->assertRedirect();

        $salida = LibroContable::where('tipo_movimiento', 'salida')->sole();
        $this->assertEquals(2000.0, (float) $salida->monto);
        $this->assertStringContainsString('Distribuidora Pérez', $salida->descripcion);
        $this->assertEquals(8000.0, (float) LibroContable::orderByDesc('id')->value('saldo_despues'));
    }

    public function test_no_registra_el_pago_si_el_libro_no_tiene_saldo(): void
    {
        $admin = User::factory()->admin()->create();
        Configuracion::create(['tasa_cambio' => 50]);
        $cuenta = $this->crearCuenta($admin, 100);

        $this->actingAs($admin)->post("/cuentas-por-pagar/{$cuenta->id}/pagos", [
            'monto' => 40, 'forma_pago' => 'efectivo', 'fecha' => now()->toDateString(),
        ])->assertSessionHasErrors('pago');

        $this->assertEquals(100.0, (float) $cuenta->fresh()->saldo);
        $this->assertEquals(0, PagoPorPagar::count());
        $this->assertEquals(0, LibroContable::count());
    }

    public function test_no_se_puede_eliminar_una_cuenta_con_pagos(): void
    {
        $admin = User::factory()->admin()->create();
        $this->conSaldoEnLibro();
        $cuenta = $this->crearCuenta($admin, 100);
        $this->actingAs($admin)->post("/cuentas-por-pagar/{$cuenta->id}/pagos", [
            'monto' => 10, 'forma_pago' => 'efectivo', 'fecha' => now()->toDateString(),
        ]);

        $this->actingAs($admin)->delete("/cuentas-por-pagar/{$cuenta->id}")->assertSessionHasErrors('eliminar');
        $this->assertNotNull(CuentaPorPagar::find($cuenta->id));
    }

    public function test_el_monto_no_cambia_cuando_ya_hay_pagos(): void
    {
        $admin = User::factory()->admin()->create();
        $this->conSaldoEnLibro();
        $cuenta = $this->crearCuenta($admin, 100);
        $this->actingAs($admin)->post("/cuentas-por-pagar/{$cuenta->id}/pagos", [
            'monto' => 30, 'forma_pago' => 'efectivo', 'fecha' => now()->toDateString(),
        ]);

        $this->actingAs($admin)->put("/cuentas-por-pagar/{$cuenta->id}", [
            'acreedor' => 'Distribuidora Pérez', 'tipo' => 'factura', 'monto' => 500,
            'fecha_emision' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertEquals(100.0, (float) $cuenta->fresh()->monto);
        $this->assertEquals(70.0, (float) $cuenta->fresh()->saldo);
    }

    public function test_la_pantalla_muestra_a_quien_se_le_debe(): void
    {
        $admin = User::factory()->admin()->create();
        $this->crearCuenta($admin, 100);

        $this->actingAs($admin)->get('/cuentas-por-pagar')
            ->assertOk()
            ->assertSee('Distribuidora Pérez')
            ->assertSee('¿A quién se le debe?');
    }

    public function test_un_empleado_no_puede_ver_cuentas_por_pagar(): void
    {
        $empleado = User::factory()->create();

        $this->actingAs($empleado)->get('/cuentas-por-pagar')->assertForbidden();
    }
}
