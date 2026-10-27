<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Foto del producto desde la app móvil (solo administradores). Se guarda en public/uploads/productos
 * (igual que el logo), sin necesidad de storage:link.
 */
class ProductoImagenController extends Controller
{
    private const CARPETA = 'uploads/productos';

    public function store(Request $request, int $id): JsonResponse
    {
        $producto = Producto::findOrFail($id);

        $request->validate([
            'imagen' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:6144',
        ]);

        $archivo = $request->file('imagen');
        $nombre = $producto->id.'-'.bin2hex(random_bytes(6)).'.'.$archivo->guessExtension();
        $archivo->move(public_path(self::CARPETA), $nombre);

        $this->borrarArchivoAnterior($producto);
        $producto->update(['imagen_url' => self::CARPETA.'/'.$nombre]);

        return response()->json([
            'message' => 'Imagen actualizada.',
            'data' => ['id' => $producto->id, 'imagen_url' => $producto->fresh()->imagen_url],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $producto = Producto::findOrFail($id);

        $this->borrarArchivoAnterior($producto);
        $producto->update(['imagen_url' => null]);

        return response()->json(['message' => 'Imagen eliminada.']);
    }

    /** Borra el archivo anterior solo si lo subimos nosotros (ruta relativa dentro de la carpeta). */
    private function borrarArchivoAnterior(Producto $producto): void
    {
        $actual = $producto->getRawOriginal('imagen_url');

        if ($actual && str_starts_with($actual, self::CARPETA.'/')) {
            File::delete(public_path($actual));
        }
    }
}
