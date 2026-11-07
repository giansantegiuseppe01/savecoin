<?php

namespace Tests\Feature;

use App\Models\BitacoraInventario;
use App\Models\Insumo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsumoPreparadoTest extends TestCase
{
    use RefreshDatabase;

    private function insumo(string $nombre, float $stock, float $costo = 0.01): Insumo
    {
        return Insumo::create([
            'nombre' => $nombre,
            'codigo' => strtoupper(substr($nombre, 0, 3)).'-'.uniqid(),
            'costo_unitario' => $costo,
            'stock_actual' => $stock,
            'unidad_medida' => 'G',
            'stock_minimo' => 0,
            'activo' => true,
        ]);
    }

    /** Salsa especial: 350 mayonesa + 150 pepinillo + 100 mostaza + 350 tomate + 150 cebolla = 1100 g */
    private function salsa(): array
    {
        $ing = [
            'mayonesa' => $this->insumo('Mayonesa', 5000, 0.004),
            'pepinillo' => $this->insumo('Pepinillo', 2000, 0.006),
            'mostaza' => $this->insumo('Mostaza', 1000, 0.003),
            'tomate' => $this->insumo('Salsa tomate', 5000, 0.002),
            'cebolla' => $this->insumo('Cebolla', 3000, 0.001),
        ];
        $salsa = $this->insumo('Salsa especial', 0);
        $salsa->guardarReceta(1100, [
            ['insumo_id' => $ing['mayonesa']->id, 'cantidad' => 350],
            ['insumo_id' => $ing['pepinillo']->id, 'cantidad' => 150],
            ['insumo_id' => $ing['mostaza']->id, 'cantidad' => 100],
            ['insumo_id' => $ing['tomate']->id, 'cantidad' => 350],
            ['insumo_id' => $ing['cebolla']->id, 'cantidad' => 150],
        ]);

        return [$salsa->fresh(), $ing];
    }

    public function test_cargar_un_preparado_descuenta_los_ingredientes_en_proporcion(): void
    {
        [$salsa, $ing] = $this->salsa();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/inventario/agregar-stock', [
            'insumo_id' => $salsa->id,
            'tipo_movimiento' => 'entrada',
            'cantidad' => 2200, // dos lotes
            'motivo' => 'Preparación del día',
        ])->assertOk();

        $this->assertEquals(2200.0, (float) $salsa->fresh()->stock_actual);
        $this->assertEquals(4300.0, (float) $ing['mayonesa']->fresh()->stock_actual); // -700
        $this->assertEquals(1700.0, (float) $ing['pepinillo']->fresh()->stock_actual); // -300
        $this->assertEquals(800.0, (float) $ing['mostaza']->fresh()->stock_actual);    // -200
        $this->assertEquals(4300.0, (float) $ing['tomate']->fresh()->stock_actual);    // -700
        $this->assertEquals(2700.0, (float) $ing['cebolla']->fresh()->stock_actual);   // -300

        $this->assertSame(5, BitacoraInventario::where('tipo_movimiento', 'salida')
            ->where('motivo', 'like', 'Preparación de Salsa especial%')->count());
    }

    public function test_si_falta_un_ingrediente_no_se_mueve_nada(): void
    {
        [$salsa, $ing] = $this->salsa();
        $ing['mostaza']->update(['stock_actual' => 50]); // un lote necesita 100
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/inventario/agregar-stock', [
            'insumo_id' => $salsa->id,
            'tipo_movimiento' => 'entrada',
            'cantidad' => 1100,
            'motivo' => 'Preparación',
        ])->assertStatus(400)->assertJsonPath('success', false);

        $this->assertEquals(0.0, (float) $salsa->fresh()->stock_actual);
        $this->assertEquals(5000.0, (float) $ing['mayonesa']->fresh()->stock_actual);
        $this->assertSame(0, BitacoraInventario::count());
    }

    public function test_el_costo_del_preparado_se_calcula_con_sus_ingredientes(): void
    {
        [$salsa] = $this->salsa();

        // 350*0.004 + 150*0.006 + 100*0.003 + 350*0.002 + 150*0.001 = 3.45 por lote de 1100
        $this->assertEqualsWithDelta(3.45 / 1100, (float) $salsa->costo_unitario, 0.0001);
    }

    public function test_la_app_admin_carga_un_preparado_y_lista_su_receta(): void
    {
        [$salsa, $ing] = $this->salsa();
        $admin = User::factory()->admin()->create();

        $lista = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/insumos')->assertOk()->json();
        $dato = collect($lista)->firstWhere('id', $salsa->id);
        $this->assertEquals(1100.0, $dato['rendimiento']);
        $this->assertCount(5, $dato['ingredientes']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/inventario/movimientos', [
            'insumo_id' => $salsa->id, 'tipo_movimiento' => 'entrada', 'cantidad' => 550, 'motivo' => 'Preparación',
        ])->assertOk();

        $this->assertEquals(550.0, (float) $salsa->fresh()->stock_actual);
        $this->assertEquals(4825.0, (float) $ing['mayonesa']->fresh()->stock_actual); // -175

        // Una carga con falta de stock da 422 y no mueve nada
        $ing['cebolla']->update(['stock_actual' => 10]);
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/inventario/movimientos', [
            'insumo_id' => $salsa->id, 'tipo_movimiento' => 'entrada', 'cantidad' => 550, 'motivo' => 'Preparación',
        ])->assertStatus(422);
        $this->assertEquals(550.0, (float) $salsa->fresh()->stock_actual);
    }

    public function test_descargar_un_preparado_no_devuelve_ingredientes_y_un_insumo_normal_no_cambia(): void
    {
        [$salsa, $ing] = $this->salsa();
        $salsa->update(['stock_actual' => 100]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/inventario/movimientos', [
            'insumo_id' => $salsa->id, 'tipo_movimiento' => 'salida', 'cantidad' => 40, 'motivo' => 'Merma',
        ])->assertOk();
        $this->assertEquals(5000.0, (float) $ing['mayonesa']->fresh()->stock_actual);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/inventario/movimientos', [
            'insumo_id' => $ing['mayonesa']->id, 'tipo_movimiento' => 'entrada', 'cantidad' => 100, 'motivo' => 'Compra',
        ])->assertOk();
        $this->assertEquals(5100.0, (float) $ing['mayonesa']->fresh()->stock_actual);
        $this->assertEquals(2000.0, (float) $ing['pepinillo']->fresh()->stock_actual);
    }

    public function test_crear_y_editar_un_preparado_desde_la_web(): void
    {
        $a = $this->insumo('Pan', 100);
        $b = $this->insumo('Queso', 100);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/inventario', [
            'codigo' => 'MEZ-1', 'nombre' => 'Mezcla', 'unidad_medida' => 'G', 'costo_unitario' => 0,
            'stock_actual' => 0, 'stock_minimo' => 0, 'activo' => '1',
            'ingredientes' => [
                1 => ['insumo_id' => $a->id, 'cantidad' => 30],
                2 => ['insumo_id' => $b->id, 'cantidad' => 20],
            ],
        ])->assertOk();

        $mezcla = Insumo::where('codigo', 'MEZ-1')->firstOrFail();
        $this->assertTrue($mezcla->esPreparado());
        $this->assertEquals(50.0, (float) $mezcla->rendimiento); // sin rendimiento: la suma

        // Quitar todos los ingredientes lo convierte otra vez en un insumo normal
        $this->actingAs($admin)->putJson('/inventario', [
            'id' => $mezcla->id, 'codigo' => 'MEZ-1', 'nombre' => 'Mezcla', 'unidad_medida' => 'G',
            'costo_unitario' => 0, 'stock_minimo' => 0, 'activo' => '1',
        ])->assertOk();
        $this->assertFalse($mezcla->fresh()->esPreparado());
    }
}
