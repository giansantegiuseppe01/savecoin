<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Orden;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Rutas que antes eran públicas ----------

    public function test_register_is_not_public(): void
    {
        $this->postJson('/api/register', [
            'nombre' => 'Mal',
            'apellido' => 'Actor',
            'email' => 'mal@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'rol' => 'admin',
        ])->assertUnauthorized();

        $this->assertDatabaseMissing('users', ['email' => 'mal@example.com']);
    }

    public function test_employee_cannot_register_users(): void
    {
        $empleado = User::factory()->create();

        $this->actingAs($empleado, 'sanctum')
            ->postJson('/api/register', [
                'nombre' => 'Mal',
                'apellido' => 'Actor',
                'email' => 'mal@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'rol' => 'admin',
            ])->assertForbidden();
    }

    public function test_admin_can_register_users(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/register', [
                'nombre' => 'Nuevo',
                'apellido' => 'Empleado',
                'email' => 'nuevo@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'rol' => 'empleado',
            ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'nuevo@example.com', 'rol' => 'empleado']);
    }

    public function test_clientes_requires_authentication(): void
    {
        Cliente::create(['nombre' => 'Ana', 'apellido' => 'Pérez', 'cedula' => 'V-1']);

        $this->getJson('/api/clientes')->assertUnauthorized();
        $this->postJson('/api/clientes', [])->assertUnauthorized();
        $this->deleteJson('/api/clientes/1')->assertUnauthorized();
    }

    public function test_pos_products_and_config_require_authentication(): void
    {
        $this->getJson('/api/pos/productos')->assertUnauthorized();
        $this->getJson('/api/configuracion')->assertUnauthorized();
    }

    public function test_only_admin_can_delete_clientes(): void
    {
        $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'Pérez', 'cedula' => 'V-1']);
        $empleado = User::factory()->create();

        $this->actingAs($empleado, 'sanctum')
            ->deleteJson("/api/clientes/{$cliente->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('clientes', ['id' => $cliente->id]);
    }

    public function test_employee_can_list_clientes_and_products(): void
    {
        $empleado = User::factory()->create();

        $this->actingAs($empleado, 'sanctum')->getJson('/api/clientes')->assertOk();
        $this->actingAs($empleado, 'sanctum')->getJson('/api/pos/productos')->assertOk();
    }

    public function test_employee_cannot_use_admin_endpoints(): void
    {
        $empleado = User::factory()->create();

        $this->actingAs($empleado, 'sanctum')->getJson('/api/users')->assertForbidden();
        $this->actingAs($empleado, 'sanctum')->getJson('/api/wallets')->assertForbidden();
    }

    public function test_health_check_is_public(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok');
    }

    // ---------- Login / logout ----------

    public function test_login_returns_token_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'ok@example.com']);

        $this->postJson('/api/login', ['email' => 'ok@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['success', 'token', 'user' => ['id', 'email', 'rol']]);

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'ok@example.com']);

        $this->postJson('/api/login', ['email' => 'ok@example.com', 'password' => 'incorrecta'])
            ->assertUnauthorized();
        $this->postJson('/api/login', ['email' => 'noexiste@example.com', 'password' => 'x'])
            ->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'ok@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'ok@example.com', 'password' => 'mala'])
                ->assertUnauthorized();
        }

        $this->postJson('/api/login', ['email' => 'ok@example.com', 'password' => 'mala'])
            ->assertStatus(429);
    }

    public function test_logout_requires_auth_and_revokes_token(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();

        $user = User::factory()->create();
        $token = $user->createToken('mobile-app-token')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_tokens_are_revoked_when_user_is_deleted_by_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $empleado = User::factory()->create();
        $empleado->createToken('mobile-app-token');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/users/{$empleado->id}")
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $empleado->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/users/{$admin->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$admin->id}", ['rol' => 'empleado'])
            ->assertStatus(422);

        $this->assertSame('admin', $admin->fresh()->rol);
    }

    public function test_password_change_revokes_existing_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $empleado = User::factory()->create();
        $empleado->createToken('mobile-app-token');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$empleado->id}", ['password' => 'nuevaClave123'])
            ->assertOk();

        $this->assertSame(0, $empleado->tokens()->count());
    }

    // ---------- Rutas / bugs corregidos ----------

    public function test_stock_alerts_route_is_not_shadowed_by_insumo_id(): void
    {
        $empleado = User::factory()->create();

        $this->actingAs($empleado, 'sanctum')
            ->getJson('/api/insumos/stock-alerts')
            ->assertOk()
            ->assertJsonStructure(['counters', 'low_stock_items', 'critical_items']);
    }

    public function test_employee_cannot_view_other_employees_shift(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $turno = Turno::create([
            'codigo' => 'T-1',
            'user_id' => $a->id,
            'usuario_nombre' => 'A',
            'fecha' => now()->toDateString(),
            'hora_apertura' => now(),
            'estado' => 'abierto',
            'monto_apertura' => 0,
        ]);

        $this->actingAs($b, 'sanctum')->getJson("/api/turnos/{$turno->id}")->assertNotFound();
        $this->actingAs($a, 'sanctum')->getJson("/api/turnos/{$turno->id}")->assertOk();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson("/api/turnos/{$turno->id}")->assertOk();
    }

    public function test_cors_does_not_allow_arbitrary_origins(): void
    {
        $this->assertNotContains('*', config('cors.allowed_origins'));
        $this->assertFalse(config('cors.supports_credentials'));
    }

    public function test_sanctum_tokens_expire(): void
    {
        $this->assertGreaterThan(0, config('sanctum.expiration'));
    }

    public function test_employee_cannot_read_or_modify_other_employees_orders(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $id = (string) \Illuminate\Support\Str::uuid();

        Orden::forceCreate([
            'id' => $id,
            'numero_recibo' => 'R-IDOR-1',
            'user_id' => $a->id,
            'cliente_nombre' => 'X',
            'tipo' => 'venta',
            'estado' => 'abierta',
            'subtotal' => 1,
            'total' => 1,
            'tasa_cambio' => 1,
            'fecha_hora' => now(),
        ]);

        $this->actingAs($b, 'sanctum')->getJson("/api/pos/ordenes/{$id}")->assertForbidden();
        $this->actingAs($b, 'sanctum')
            ->putJson("/api/pos/ordenes/{$id}", ['estado' => 'anulada'])
            ->assertForbidden();
        $this->actingAs($b, 'sanctum')->getJson('/api/pos/ordenes')
            ->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($a, 'sanctum')->getJson("/api/pos/ordenes/{$id}")->assertOk();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson("/api/pos/ordenes/{$id}")->assertOk();
    }

    public function test_responses_include_security_headers(): void
    {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_deleting_employee_keeps_orders_and_shifts(): void
    {
        $admin = User::factory()->admin()->create();
        $empleado = User::factory()->create();

        $turno = Turno::create([
            'codigo' => 'T-DEL',
            'user_id' => $empleado->id,
            'usuario_nombre' => 'E',
            'fecha' => now()->toDateString(),
            'hora_apertura' => now(),
            'estado' => 'abierto',
            'monto_apertura' => 0,
        ]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/users/{$empleado->id}")->assertOk();

        $this->assertSoftDeleted('users', ['id' => $empleado->id]);
        $this->assertDatabaseHas('turnos', ['id' => $turno->id]);
    }
}
