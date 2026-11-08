<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Orden;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClienteDeudaTest extends TestCase
{
    use RefreshDatabase;

    private function orden(User $u, ?Cliente $c, string $estado, float $total, string $tipo = 'venta'): Orden
    {
        return Orden::forceCreate([
            'id' => (string) Str::uuid(),
            'numero_recibo' => 'ORD-'.Str::random(6),
            'user_id' => $u->id,
            'cliente_id' => $c?->id,
            'cliente_nombre' => $c?->nombre ?? 'General',
            'tipo' => $tipo,
            'estado' => $estado,
            'subtotal' => $total,
            'total' => $total,
            'tasa_cambio' => 100,
            'fecha_hora' => now(),
            'is_local' => false,
        ]);
    }

    public function test_el_listado_separa_lo_pagado_de_lo_que_se_debe(): void
    {
        $admin = User::factory()->admin()->create();
        $ana = Cliente::create(['nombre' => 'Ana', 'apellido' => 'Pérez', 'cedula' => 'V-1']);
        $luis = Cliente::create(['nombre' => 'Luis', 'apellido' => 'Gómez', 'cedula' => 'V-2']);

        $this->orden($admin, $ana, 'cerrada', 10);
        $this->orden($admin, $ana, 'completada', 5);
        $this->orden($admin, $ana, 'abierta', 6);      // debe
        $this->orden($admin, $ana, 'abierta', 4);      // debe
        $this->orden($admin, $ana, 'anulada', 50);     // no cuenta
        $this->orden($admin, $ana, 'completada', 3, 'reembolso'); // no cuenta
        $this->orden($admin, $luis, 'cerrada', 8);     // paga todo

        $resp = $this->actingAs($admin)->get('/clientes')->assertOk();

        $filas = $resp->viewData('clientes')->keyBy('cedula');
        $this->assertEquals(15.0, $filas['V-1']->total_gastado);
        $this->assertEquals(10.0, $filas['V-1']->deuda);
        $this->assertSame(2, $filas['V-1']->tickets_abiertos_count);
        $this->assertEquals(8.0, $filas['V-2']->total_gastado);
        $this->assertEquals(0.0, $filas['V-2']->deuda);

        $this->assertEquals(23.0, $resp->viewData('totalPagado'));
        $this->assertEquals(10.0, $resp->viewData('totalPorCobrar'));
        $this->assertSame(1, $resp->viewData('clientesQueDeben'));
        $this->assertSame(2, $resp->viewData('ticketsAbiertos'));
    }

    public function test_el_filtro_muestra_solo_los_clientes_que_deben(): void
    {
        $admin = User::factory()->admin()->create();
        $ana = Cliente::create(['nombre' => 'Ana', 'apellido' => 'Pérez', 'cedula' => 'V-1']);
        $luis = Cliente::create(['nombre' => 'Luis', 'apellido' => 'Gómez', 'cedula' => 'V-2']);
        $this->orden($admin, $ana, 'abierta', 6);
        $this->orden($admin, $luis, 'cerrada', 8);

        $resp = $this->actingAs($admin)->get('/clientes?con_deuda=1')->assertOk();

        $this->assertSame(['V-1'], $resp->viewData('clientes')->pluck('cedula')->all());
    }
}
