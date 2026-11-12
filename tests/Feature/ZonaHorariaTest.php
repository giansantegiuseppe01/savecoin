<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

class ZonaHorariaTest extends TestCase
{
    public function test_el_servidor_usa_la_hora_de_venezuela(): void
    {
        $this->assertSame('America/Caracas', config('app.timezone'));
        $this->assertSame('America/Caracas', now()->getTimezone()->getName());
    }

    public function test_una_fecha_con_otra_zona_se_convierte_a_la_del_servidor(): void
    {
        // 14:00 UTC son las 10:00 en Caracas
        $fecha = Carbon::parse('2026-11-11T14:00:00+00:00')->setTimezone(config('app.timezone'));

        $this->assertSame('2026-11-11 10:00:00', $fecha->format('Y-m-d H:i:s'));
    }
}
