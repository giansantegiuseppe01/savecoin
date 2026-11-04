<?php

namespace Tests\Feature;

use App\Models\CategoriaLibroContable;
use App\Models\LibroContable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLibroContableTest extends TestCase
{
    use RefreshDatabase;

    /** Comparación numérica tolerante (el JSON puede traer 1000 o 1000.0). */
    private function igual(float $esperado): \Closure
    {
        return fn ($valor) => abs((float) $valor - $esperado) < 0.0001;
    }

    private function movimiento(User $admin, array $datos)
    {
        return $this->actingAs($admin, 'sanctum')->postJson('/api/admin/libro-contable', $datos + [
            'fecha' => now()->toDateString(),
            'descripcion' => 'Prueba',
        ]);
    }

    public function test_el_empleado_no_puede_ver_ni_registrar_movimientos(): void
    {
        $empleado = User::factory()->create();

        $this->actingAs($empleado, 'sanctum')->getJson('/api/admin/libro-contable')->assertForbidden();
        $this->movimiento($empleado, ['tipo_movimiento' => 'entrada', 'monto' => 100])->assertForbidden();
        $this->assertSame(0, LibroContable::count());
    }

    public function test_el_admin_registra_entradas_y_salidas_con_saldo_corrido(): void
    {
        $admin = User::factory()->admin()->create();
        $categoria = CategoriaLibroContable::create(['nombre' => 'Compras', 'activo' => true]);

        $this->movimiento($admin, ['tipo_movimiento' => 'entrada', 'monto' => 1000, 'descripcion' => 'Capital'])
            ->assertCreated()->assertJsonPath('saldo_actual', $this->igual(1000));

        $this->movimiento($admin, [
            'tipo_movimiento' => 'salida', 'monto' => 250.5, 'descripcion' => 'Compra de pan',
            'categoria_id' => $categoria->id, 'referencia' => 'F-123',
        ])->assertCreated()
            ->assertJsonPath('data.saldo_despues', $this->igual(749.5))
            ->assertJsonPath('data.categoria', 'Compras')
            ->assertJsonPath('data.referencia', 'F-123');

        $lista = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/libro-contable')->assertOk();
        $lista->assertJsonPath('saldo_actual', $this->igual(749.5))
            ->assertJsonPath('total_entradas', $this->igual(1000))
            ->assertJsonPath('total_salidas', $this->igual(250.5))
            ->assertJsonCount(2, 'movimientos')
            ->assertJsonPath('categorias.0.nombre', 'Compras');

        $this->assertEquals($admin->id, LibroContable::latest('id')->first()->user_id);
    }

    public function test_una_salida_no_puede_superar_el_saldo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->movimiento($admin, ['tipo_movimiento' => 'entrada', 'monto' => 100])->assertCreated();
        $this->movimiento($admin, ['tipo_movimiento' => 'salida', 'monto' => 100.01])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Saldo insuficiente'));

        // Justo el saldo disponible sí se permite
        $this->movimiento($admin, ['tipo_movimiento' => 'salida', 'monto' => 100])
            ->assertCreated()->assertJsonPath('saldo_actual', $this->igual(0));

        $this->assertSame(2, LibroContable::count());
    }

    public function test_un_movimiento_con_fecha_anterior_recalcula_los_saldos_posteriores(): void
    {
        $admin = User::factory()->admin()->create();
        $hoy = now()->toDateString();
        $ayer = now()->subDay()->toDateString();

        $this->movimiento($admin, ['tipo_movimiento' => 'entrada', 'monto' => 500, 'fecha' => $hoy])->assertCreated();
        // Se registra después, pero con fecha de ayer: queda antes en el libro
        $this->movimiento($admin, ['tipo_movimiento' => 'entrada', 'monto' => 200, 'fecha' => $ayer])->assertCreated();

        $orden = LibroContable::orderBy('fecha')->orderBy('id')->get();
        $this->assertEquals([200.0, 700.0], $orden->pluck('saldo_despues')->map(fn ($v) => (float) $v)->all());

        // Una salida de ayer no puede usar el dinero que entró hoy
        $this->movimiento($admin, ['tipo_movimiento' => 'salida', 'monto' => 300, 'fecha' => $ayer])->assertStatus(422);
    }

    public function test_validaciones(): void
    {
        $admin = User::factory()->admin()->create();

        $this->movimiento($admin, ['tipo_movimiento' => 'entrada', 'monto' => 0])->assertStatus(422)->assertJsonValidationErrors('monto');
        $this->movimiento($admin, ['tipo_movimiento' => 'otro', 'monto' => 10])->assertStatus(422)->assertJsonValidationErrors('tipo_movimiento');
        $this->movimiento($admin, ['tipo_movimiento' => 'entrada', 'monto' => 10, 'descripcion' => ''])->assertStatus(422)->assertJsonValidationErrors('descripcion');
        $this->movimiento($admin, ['tipo_movimiento' => 'entrada', 'monto' => 10, 'fecha' => now()->addDays(2)->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('fecha');
        $this->movimiento($admin, ['tipo_movimiento' => 'entrada', 'monto' => 10, 'categoria_id' => 999])
            ->assertStatus(422)->assertJsonValidationErrors('categoria_id');

        $this->assertSame(0, LibroContable::count());
    }

    public function test_eliminar_un_movimiento_desde_la_web_recalcula_los_saldos(): void
    {
        $admin = User::factory()->admin()->create();

        $this->movimiento($admin, ['tipo_movimiento' => 'entrada', 'monto' => 1000])->assertCreated();
        $this->movimiento($admin, ['tipo_movimiento' => 'salida', 'monto' => 300])->assertCreated();
        $ultimo = $this->movimiento($admin, ['tipo_movimiento' => 'entrada', 'monto' => 50])->assertCreated()->json('data.id');
        $salida = LibroContable::where('tipo_movimiento', 'salida')->value('id');

        // Se elimina la salida de 300: el saldo final pasa de 750 a 1050
        $this->actingAs($admin)->deleteJson("/libro-contable/{$salida}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('saldo_actual', $this->igual(1050));

        $this->assertSame(2, LibroContable::count());
        $this->assertEquals(1050.0, (float) LibroContable::find($ultimo)->saldo_despues);
    }
}
