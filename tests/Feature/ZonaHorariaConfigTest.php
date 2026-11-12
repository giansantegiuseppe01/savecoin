<?php

namespace Tests\Feature;

use App\Models\AjusteSistema;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZonaHorariaConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cambia_la_zona_horaria_del_servidor(): void
    {
        $admin = User::factory()->admin()->create();
        $empleado = User::factory()->create();

        $this->actingAs($empleado)->putJson('/configuracion/zona-horaria', ['zona_horaria' => 'Europe/Madrid'])->assertForbidden();

        $this->actingAs($admin)->putJson('/configuracion/zona-horaria', ['zona_horaria' => 'Europe/Madrid'])->assertOk();

        $this->assertSame('Europe/Madrid', AjusteSistema::obtener('zona_horaria'));
        $this->assertSame('Europe/Madrid', config('app.timezone'));

        $this->actingAs($admin)->putJson('/configuracion/zona-horaria', ['zona_horaria' => 'Zona/Inventada'])->assertStatus(422);
    }
}
