<?php

namespace Tests\Feature;

use App\Models\BitacoraInventario;
use App\Models\Insumo;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminMovilTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Las pruebas de imagen escriben en public/uploads/productos: se limpian
        foreach (File::glob(public_path('uploads/productos/*')) as $archivo) {
            File::delete($archivo);
        }

        parent::tearDown();
    }

    private function insumo(float $stock = 10): Insumo
    {
        return Insumo::create([
            'nombre' => 'Pan',
            'codigo' => 'INS-'.Str::random(5),
            'costo_unitario' => 1,
            'stock_actual' => $stock,
            'unidad_medida' => 'und',
            'stock_minimo' => 0,
        ]);
    }

    private function producto(): Producto
    {
        return Producto::create([
            'nombre' => 'Perro',
            'codigo' => 'PRD-'.Str::random(5),
            'precio_venta' => 3,
        ]);
    }

    public function test_el_empleado_no_puede_usar_la_administracion_movil(): void
    {
        $empleado = User::factory()->create();
        $insumo = $this->insumo();
        $producto = $this->producto();

        $this->actingAs($empleado, 'sanctum')->getJson('/api/admin/insumos')->assertForbidden();
        $this->actingAs($empleado, 'sanctum')
            ->postJson('/api/admin/inventario/movimientos', [
                'insumo_id' => $insumo->id, 'tipo_movimiento' => 'entrada', 'cantidad' => 5, 'motivo' => 'compra',
            ])->assertForbidden();
        $this->actingAs($empleado, 'sanctum')
            ->postJson("/api/admin/productos/{$producto->id}/imagen", ['imagen' => UploadedFile::fake()->image('p.jpg')])
            ->assertForbidden();

        $this->assertEquals(10.0, (float) $insumo->fresh()->stock_actual);
        $this->assertNull($producto->fresh()->getRawOriginal('imagen_url'));
    }

    public function test_el_admin_carga_y_descarga_inventario_con_bitacora(): void
    {
        $admin = User::factory()->admin()->create();
        $insumo = $this->insumo(10);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/insumos')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.nombre', 'Pan');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/inventario/movimientos', [
                'insumo_id' => $insumo->id, 'tipo_movimiento' => 'entrada', 'cantidad' => 5, 'motivo' => 'Compra del día',
            ])->assertOk();
        $this->assertEquals(15.0, (float) $insumo->fresh()->stock_actual);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/inventario/movimientos', [
                'insumo_id' => $insumo->id, 'tipo_movimiento' => 'salida', 'cantidad' => 4, 'motivo' => 'Merma',
            ])->assertOk();
        $this->assertEquals(11.0, (float) $insumo->fresh()->stock_actual);

        $this->assertSame(2, BitacoraInventario::where('insumo_id', $insumo->id)->count());
        $ultima = BitacoraInventario::where('insumo_id', $insumo->id)->latest('id')->first();
        $this->assertSame('salida', $ultima->tipo_movimiento);
        $this->assertSame('Merma', $ultima->motivo);
        $this->assertEquals($admin->id, $ultima->user_id);
        $this->assertEquals(15.0, (float) $ultima->cantidad_anterior);
        $this->assertEquals(11.0, (float) $ultima->cantidad_nueva);
    }

    public function test_una_descarga_no_puede_dejar_el_stock_en_negativo_y_exige_motivo(): void
    {
        $admin = User::factory()->admin()->create();
        $insumo = $this->insumo(3);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/inventario/movimientos', [
                'insumo_id' => $insumo->id, 'tipo_movimiento' => 'salida', 'cantidad' => 4, 'motivo' => 'Merma',
            ])->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/inventario/movimientos', [
                'insumo_id' => $insumo->id, 'tipo_movimiento' => 'salida', 'cantidad' => 1,
            ])->assertStatus(422)->assertJsonValidationErrors('motivo');

        $this->assertEquals(3.0, (float) $insumo->fresh()->stock_actual);
        $this->assertSame(0, BitacoraInventario::count());
    }

    public function test_el_admin_sube_la_foto_de_un_producto_y_la_app_la_recibe_como_url_completa(): void
    {
        $admin = User::factory()->admin()->create();
        $producto = $this->producto();

        $respuesta = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/productos/{$producto->id}/imagen", ['imagen' => UploadedFile::fake()->image('foto.jpg', 400, 400)])
            ->assertOk();

        $ruta = $producto->fresh()->getRawOriginal('imagen_url');
        $this->assertStringStartsWith('uploads/productos/', $ruta);
        $this->assertFileExists(public_path($ruta));
        $this->assertStringStartsWith('http', $respuesta->json('data.imagen_url'));
        $this->assertStringEndsWith($ruta, $respuesta->json('data.imagen_url'));

        // El catálogo del POS trae la URL completa
        $fila = collect($this->actingAs($admin, 'sanctum')->getJson('/api/pos/productos')->json())->firstWhere('id', $producto->id);
        $this->assertStringEndsWith($ruta, $fila['imagen_url']);

        // Al subir otra foto se borra la anterior
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/productos/{$producto->id}/imagen", ['imagen' => UploadedFile::fake()->image('otra.png', 300, 300)])
            ->assertOk();
        $this->assertFileDoesNotExist(public_path($ruta));
        $this->assertFileExists(public_path($producto->fresh()->getRawOriginal('imagen_url')));
    }

    public function test_solo_se_aceptan_imagenes(): void
    {
        $admin = User::factory()->admin()->create();
        $producto = $this->producto();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/productos/{$producto->id}/imagen", [
                'imagen' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php'),
            ])->assertStatus(422)->assertJsonValidationErrors('imagen');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/productos/{$producto->id}/imagen", [])
            ->assertStatus(422);
    }

    public function test_editar_un_producto_desde_la_web_no_borra_su_foto(): void
    {
        $admin = User::factory()->admin()->create();
        $producto = $this->producto();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/productos/{$producto->id}/imagen", ['imagen' => UploadedFile::fake()->image('foto.jpg')])
            ->assertOk();
        $ruta = $producto->fresh()->getRawOriginal('imagen_url');

        // El formulario web no envía imagen_url
        $this->actingAs($admin)->putJson("/productos/{$producto->id}", [
            'nombre' => 'Perro grande',
            'codigo' => $producto->codigo,
            'precio_venta' => 4,
        ])->assertOk();

        $this->assertSame($ruta, $producto->fresh()->getRawOriginal('imagen_url'));
    }

    public function test_el_admin_puede_quitar_la_foto(): void
    {
        $admin = User::factory()->admin()->create();
        $producto = $this->producto();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/productos/{$producto->id}/imagen", ['imagen' => UploadedFile::fake()->image('foto.jpg')])
            ->assertOk();
        $ruta = $producto->fresh()->getRawOriginal('imagen_url');

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/productos/{$producto->id}/imagen")->assertOk();

        $this->assertNull($producto->fresh()->getRawOriginal('imagen_url'));
        $this->assertFileDoesNotExist(public_path($ruta));
    }
}
