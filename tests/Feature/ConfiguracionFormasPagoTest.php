<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\FormaPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguracionFormasPagoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gestiona_formas_de_pago_y_la_api_las_entrega(): void
    {
        $admin = User::factory()->admin()->create();
        $empleado = User::factory()->create();
        Configuracion::create(['nombre_empresa' => 'Baron Burguers', 'rif' => 'J-1', 'telefono' => '0412', 'tasa_cambio' => 40]);

        $this->actingAs($empleado)->postJson('/configuracion/formas-pago', ['nombre' => 'X', 'datos' => 'Y'])->assertForbidden();

        $this->actingAs($admin)->postJson('/configuracion/formas-pago', [
            'nombre' => 'Pago Móvil', 'datos' => "Banco: Venezuela\nTel: 0412", 'activo' => true,
        ])->assertOk();
        $this->actingAs($admin)->postJson('/configuracion/formas-pago', [
            'nombre' => 'Zelle', 'datos' => 'a@b.com', 'activo' => false,
        ])->assertOk();
        $this->actingAs($admin)->postJson('/configuracion/formas-pago', ['nombre' => '', 'datos' => ''])->assertStatus(422);

        $this->actingAs($admin)->get('/configuracion')->assertOk()->assertSee('Pago Móvil');

        $r = $this->actingAs($empleado, 'sanctum')->getJson('/api/configuracion')->assertOk();
        $r->assertJsonPath('nombre_empresa', 'Baron Burguers');
        $r->assertJsonCount(1, 'formas_pago'); // la inactiva no se envía
        $r->assertJsonPath('formas_pago.0.nombre', 'Pago Móvil');

        $fp = FormaPago::where('nombre', 'Zelle')->first();
        $this->actingAs($admin)->putJson("/configuracion/formas-pago/{$fp->id}", ['nombre' => 'Zelle', 'datos' => 'c@d.com', 'activo' => true])->assertOk();
        $this->assertSame(2, FormaPago::where('activo', true)->count());
        $this->actingAs($admin)->deleteJson("/configuracion/formas-pago/{$fp->id}")->assertOk();
        $this->assertSame(1, FormaPago::count());
    }
}
