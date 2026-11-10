<?php

namespace Tests\Feature;

use App\Models\Insumo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostoPreparadoAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    private function insumo(string $nombre, float $costo): Insumo
    {
        return Insumo::create([
            'nombre' => $nombre,
            'codigo' => strtoupper(substr($nombre, 0, 3)).'-'.uniqid(),
            'costo_unitario' => $costo,
            'stock_actual' => 0,
            'unidad_medida' => 'G',
            'stock_minimo' => 0,
            'activo' => true,
        ]);
    }

    public function test_el_costo_de_un_preparado_se_calcula_aunque_se_envie_otro(): void
    {
        $admin = User::factory()->admin()->create();
        $mayo = $this->insumo('Mayonesa', 0.004);
        $tomate = $this->insumo('Tomate', 0.002);

        // Se envía un costo manual (999): el sistema lo ignora y usa la receta
        $this->actingAs($admin)->postJson('/inventario', [
            'codigo' => 'SALSA-1', 'nombre' => 'Salsa especial', 'unidad_medida' => 'G',
            'costo_unitario' => 999, 'stock_actual' => 0, 'stock_minimo' => 0,
            'rendimiento' => 1000,
            'ingredientes' => [
                ['insumo_id' => $mayo->id, 'cantidad' => 500],
                ['insumo_id' => $tomate->id, 'cantidad' => 500],
            ],
        ])->assertOk();

        $salsa = Insumo::where('codigo', 'SALSA-1')->firstOrFail();
        // (500*0.004 + 500*0.002) / 1000 = 0.003
        $this->assertEqualsWithDelta(0.003, (float) $salsa->costo_unitario, 0.00001);
    }

    public function test_un_insumo_simple_sigue_pidiendo_costo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/inventario', [
            'codigo' => 'SIMPLE-1', 'nombre' => 'Harina', 'unidad_medida' => 'G',
            'stock_actual' => 0, 'stock_minimo' => 0,
        ])->assertStatus(422);
    }
}
