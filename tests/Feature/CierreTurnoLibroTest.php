<?php

namespace Tests\Feature;

use App\Models\LibroContable;
use App\Models\Orden;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CierreTurnoLibroTest extends TestCase
{
    use RefreshDatabase;

    private function turnoConVentas(User $user, array $ventas): Turno
    {
        $turno = Turno::create([
            'codigo' => 'T-'.Str::random(6),
            'user_id' => $user->id,
            'usuario_nombre' => 'Empleado',
            'fecha' => now()->toDateString(),
            'hora_apertura' => now(),
            'estado' => 'abierto',
            'monto_apertura' => 0,
        ]);

        foreach ($ventas as $i => [$totalUsd, $tasa]) {
            Orden::forceCreate([
                'id' => (string) Str::uuid(),
                'numero_recibo' => 'ORD-T'.$i.Str::random(4),
                'user_id' => $user->id,
                'turno_id' => $turno->id,
                'cliente_nombre' => 'General',
                'tipo' => 'venta',
                'estado' => 'cerrada',
                'subtotal' => $totalUsd,
                'total' => $totalUsd,
                'tasa_cambio' => $tasa,
                'fecha_hora' => now(),
                'is_local' => false,
            ]);
        }

        return $turno;
    }

    public function test_el_cierre_de_turno_asienta_en_bolivares_con_la_tasa_de_cada_orden(): void
    {
        $empleado = User::factory()->create();
        // 10 USD a 650 + 5 USD a 700 = 6.500 + 3.500 = 10.000 Bs
        $this->turnoConVentas($empleado, [[10, 650], [5, 700]]);

        $this->actingAs($empleado, 'sanctum')
            ->postJson('/api/turnos/cerrar')
            ->assertOk();

        $asiento = LibroContable::firstOrFail();
        $this->assertSame('entrada', $asiento->tipo_movimiento);
        $this->assertEquals(10000.0, (float) $asiento->monto);
        $this->assertEquals(10000.0, (float) $asiento->saldo_despues);
        $this->assertStringContainsString('USD 15.00', $asiento->descripcion);
    }

    public function test_el_comando_corrige_asientos_antiguos_hechos_en_usd(): void
    {
        $empleado = User::factory()->create();
        $turno = $this->turnoConVentas($empleado, [[346.16, 658.38]]);
        $turno->cerrar();

        // Asiento como lo dejaba la versión anterior: el monto en USD
        $viejo = LibroContable::create([
            'fecha' => now()->toDateString(),
            'tipo_movimiento' => 'entrada',
            'monto' => 346.16,
            'descripcion' => 'TURNO EMPLEADO',
            'saldo_despues' => 346.16,
            'referencia' => 'turno_'.$turno->id,
            'user_id' => $empleado->id,
        ]);

        $this->artisan('libro:corregir-turnos')->assertSuccessful();
        $this->assertEquals(346.16, (float) $viejo->fresh()->monto, 'Sin --aplicar no debe cambiar nada');

        $this->artisan('libro:corregir-turnos', ['--aplicar' => true])->assertSuccessful();

        $esperado = round(346.16 * 658.38, 2);
        $this->assertEquals($esperado, (float) $viejo->fresh()->monto);
        $this->assertEquals($esperado, (float) $viejo->fresh()->saldo_despues);
    }
}
