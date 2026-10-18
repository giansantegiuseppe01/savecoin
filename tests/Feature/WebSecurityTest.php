<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Orden;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'ok@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'ok@example.com', 'password' => 'mala'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'ok@example.com', 'password' => 'mala'])
            ->assertStatus(429);
    }

    public function test_logout_invalidates_session(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
    }

    public function test_dashboard_is_admin_only(): void
    {
        $empleado = User::factory()->create();

        $this->actingAs($empleado)->get('/dashboard')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertOk();
    }

    public function test_colaboradores_endpoint_works_with_current_schema(): void
    {
        $admin = User::factory()->admin()->create(['nombre' => 'Ana', 'apellido' => 'Gómez']);

        $this->actingAs($admin)
            ->getJson('/api/colaboradores')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Ana Gómez']);
    }

    public function test_refund_is_rejected_for_already_voided_receipt(): void
    {
        $admin = User::factory()->admin()->create();
        $orden = $this->crearOrden($admin, ['estado' => 'anulada']);

        $this->actingAs($admin)->post("/recibos/{$orden->id}/reembolso")
            ->assertSessionHas('error');

        $this->assertSame(0, Orden::where('tipo', 'reembolso')->count());
    }

    public function test_refund_is_rejected_for_refund_receipts(): void
    {
        $admin = User::factory()->admin()->create();
        $orden = $this->crearOrden($admin, ['tipo' => 'reembolso', 'estado' => 'completada']);

        $this->actingAs($admin)->post("/recibos/{$orden->id}/reembolso")
            ->assertSessionHas('error');

        $this->assertSame(1, Orden::count());
    }

    public function test_voiding_an_already_voided_receipt_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $orden = $this->crearOrden($admin, ['estado' => 'anulada']);

        $this->actingAs($admin)->post("/recibos/{$orden->id}/anular")
            ->assertSessionHas('error');
    }

    private function crearOrden(User $user, array $attrs = []): Orden
    {
        // "id" no es fillable (en PostgreSQL lo genera la BD); en SQLite hay que fijarlo.
        $orden = (new Orden)->forceFill(array_merge([
            'id' => 'orden-'.uniqid(),
            'numero_recibo' => 'V'.random_int(10000000, 99999999),
            'user_id' => $user->id,
            'tipo' => 'venta',
            'subtotal' => 10,
            'descuento' => 0,
            'impuesto' => 0,
            'total' => 10,
            'tasa_cambio' => 1,
            'estado' => 'cerrada',
            'metodo_pago' => 'efectivo',
            'fecha_hora' => now(),
        ], $attrs));
        $orden->save();

        return $orden;
    }

    public function test_product_search_does_not_bypass_state_filter(): void
    {
        $admin = User::factory()->admin()->create();

        Producto::create(['nombre' => 'Pizza activa', 'codigo' => 'P1', 'precio_venta' => 5, 'activo' => true]);
        Producto::create(['nombre' => 'Pizza inactiva', 'codigo' => 'P2', 'precio_venta' => 5, 'activo' => false]);

        $response = $this->actingAs($admin)->get('/productos?buscar=Pizza&estado=activo');

        $response->assertOk()->assertSee('Pizza activa')->assertDontSee('Pizza inactiva');
    }

    public function test_blade_escapes_names_in_inline_handlers(): void
    {
        $admin = User::factory()->admin()->create();

        Cliente::create([
            'nombre' => "');alert(document.cookie);//",
            'apellido' => 'X',
            'cedula' => 'V-99',
        ]);

        $html = $this->actingAs($admin)->get('/clientes')->assertOk()->getContent();

        // El payload no debe aparecer sin escapar dentro del atributo onclick
        $this->assertStringNotContainsString("'');alert(document.cookie);//", $html);
        $this->assertStringNotContainsString("alert(document.cookie);//'", $html);
    }
}
