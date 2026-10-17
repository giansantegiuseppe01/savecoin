<?php

namespace App\Http\Controllers;

use App\Models\CategoriaLibroContable;
use Illuminate\Http\Request;

class CategoriaLibroContableController extends Controller
{
    public function index()
    {
        $categorias = CategoriaLibroContable::orderBy('nombre')->get();

        return response()->json($categorias);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:categoria_libro_contables',
            'descripcion' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
            'icono' => 'nullable|string|max:50',
        ]);

        $categoria = CategoriaLibroContable::create([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'color' => $validated['color'] ?? '#6c757d',
            'icono' => $validated['icono'] ?? 'bi-tag',
            'activo' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Categoría creada exitosamente',
            'data' => $categoria,
        ]);
    }

    public function update(Request $request, $id)
    {
        $categoria = CategoriaLibroContable::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:categoria_libro_contables,nombre,'.$id,
            'descripcion' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
            'icono' => 'nullable|string|max:50',
            'activo' => 'nullable|boolean',
        ]);

        $categoria->update([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'color' => $validated['color'] ?? '#6c757d',
            'icono' => $validated['icono'] ?? 'bi-tag',
            'activo' => $validated['activo'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Categoría actualizada exitosamente',
            'data' => $categoria,
        ]);
    }

    public function destroy($id)
    {
        $categoria = CategoriaLibroContable::findOrFail($id);

        // Verificar si tiene movimientos asociados
        if ($categoria->movimientos()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar esta categoría porque tiene movimientos asociados',
            ], 400);
        }

        $categoria->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada exitosamente',
        ]);
    }
}
