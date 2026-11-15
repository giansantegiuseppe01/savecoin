<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CuentaPorPagar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdenTablasTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_listas_paginadas_cargan_con_cualquier_orden(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            '/libro-contable?orden=monto&dir=asc',
            '/productos?orden=precio&dir=desc',
            '/inventario?orden=stock&dir=asc',
            '/clientes?orden=debe&dir=desc',
            '/recibos?orden=total&dir=asc',
            '/cuentas-por-pagar?orden=saldo&dir=desc',
            '/clientes?orden=columna_inventada&dir=asc', // clave no permitida: usa el orden por defecto
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_ordena_todas_las_paginas_no_solo_la_visible(): void
    {
        $admin = User::factory()->admin()->create();
        // 16 cuentas: con 15 por página, la de menor saldo queda en la página 2
        foreach (range(1, 16) as $i) {
            CuentaPorPagar::create([
                'acreedor' => 'Proveedor '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'tipo' => 'factura', 'monto' => $i * 10, 'saldo' => $i * 10,
                'estado' => 'pendiente', 'fecha_emision' => now()->toDateString(),
            ]);
        }

        $this->actingAs($admin)->get('/cuentas-por-pagar?orden=saldo&dir=asc&page=1')
            ->assertOk()
            ->assertSeeInOrder(['$10.00', '$20.00']);

        // Descendente: el primero de la página 1 es el de mayor saldo (160)
        $this->actingAs($admin)->get('/cuentas-por-pagar?orden=saldo&dir=desc')
            ->assertOk()
            ->assertSeeInOrder(['$160.00', '$150.00']);
    }

    public function test_orden_por_nombre_no_distingue_mayusculas(): void
    {
        $admin = User::factory()->admin()->create();
        Cliente::create(['nombre' => 'luis', 'apellido' => 'x', 'cedula' => 'V-1']);
        Cliente::create(['nombre' => 'Ana', 'apellido' => 'x', 'cedula' => 'V-2']);

        $this->actingAs($admin)->get('/clientes?orden=nombre&dir=asc')
            ->assertOk()
            ->assertSeeInOrder(['Ana', 'luis']);
    }
}
