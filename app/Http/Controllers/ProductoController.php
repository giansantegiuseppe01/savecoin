<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\Producto;
use App\Models\ProductoInsumo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $query = Producto::query();

        if ($request->input('buscar')) {
            $buscar = $request->input('buscar');
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', '%'.$buscar.'%')
                    ->orWhere('codigo', 'like', '%'.$buscar.'%');
            });
        }

        if ($request->input('categoria')) {
            $query->where('categoria', $request->input('categoria'));
        }

        if ($request->input('estado') === 'activo') {
            $query->where('activo', true);
        } elseif ($request->input('estado') === 'inactivo') {
            $query->where('activo', false);
        }

        $productos = $query->with('insumos')->orderBy('nombre')->paginate(20);

        // KPIs
        $totalProductos = Producto::count();
        $productosActivos = Producto::where('activo', true)->count();
        $productosCompuestos = Producto::whereHas('insumos')->count();

        $categorias = Producto::distinct()->pluck('categoria')->filter();

        return view('productos.index', compact(
            'productos',
            'totalProductos',
            'productosActivos',
            'productosCompuestos',
            'categorias'
        ));
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'codigo' => 'required|string|max:50|unique:productos,codigo',
                'descripcion' => 'nullable|string',
                'precio_venta' => 'required|numeric|min:0',
                'categoria' => 'nullable|string|max:100',
                'imagen_url' => 'nullable|url',
                'tiempo_preparacion_minutos' => 'nullable|integer|min:0',
                'insumos' => 'array',
                'insumos.*.insumo_id' => 'exists:insumos,id',
                'insumos.*.cantidad_requerida' => 'numeric|min:0.0001',
                'insumos.*.unidad_medida' => 'nullable|string|max:50',
            ]);

            $preparadoEnCocina = $request->has('preparado_en_cocina') && $request->preparado_en_cocina == '1';
            $esCompuesto = $request->has('es_compuesto') && $request->es_compuesto == '1';

            // Calcular costo de producción si es compuesto
            $costoProduccion = 0;
            if ($esCompuesto && isset($validated['insumos'])) {
                foreach ($validated['insumos'] as $insumoData) {
                    $insumo = Insumo::findOrFail($insumoData['insumo_id']);
                    $costoProduccion += $insumo->costo_unitario * $insumoData['cantidad_requerida'];
                }
            }

            $producto = Producto::create([
                'nombre' => $validated['nombre'],
                'codigo' => $validated['codigo'],
                'descripcion' => $validated['descripcion'] ?? null,
                'precio_venta' => $validated['precio_venta'],
                'costo_produccion' => $costoProduccion,
                'categoria' => $validated['categoria'] ?? null,
                'imagen_url' => $validated['imagen_url'] ?? null,
                'activo' => true,
                'preparado_en_cocina' => $preparadoEnCocina,
                'tiempo_preparacion_minutos' => $validated['tiempo_preparacion_minutos'] ?? null,
            ]);

            // Guardar insumos si es compuesto
            if ($esCompuesto && isset($validated['insumos'])) {
                foreach ($validated['insumos'] as $insumoData) {
                    // Obtener unidad_medida del insumo si no se proporcionó
                    $unidadMedida = $insumoData['unidad_medida'] ?? null;
                    if (! $unidadMedida) {
                        $insumo = Insumo::find($insumoData['insumo_id']);
                        $unidadMedida = $insumo ? $insumo->unidad_medida : 'g';
                    }

                    ProductoInsumo::create([
                        'producto_id' => $producto->id,
                        'insumo_id' => $insumoData['insumo_id'],
                        'cantidad_requerida' => $insumoData['cantidad_requerida'],
                        'unidad_medida' => $unidadMedida,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Producto creado exitosamente.',
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al crear producto.',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'codigo' => 'required|string|max:50|unique:productos,codigo,'.$id,
                'descripcion' => 'nullable|string',
                'precio_venta' => 'required|numeric|min:0',
                'categoria' => 'nullable|string|max:100',
                'imagen_url' => 'nullable|url',
                'tiempo_preparacion_minutos' => 'nullable|integer|min:0',
                'insumos' => 'array',
                'insumos.*.insumo_id' => 'exists:insumos,id',
                'insumos.*.cantidad_requerida' => 'numeric|min:0.0001',
                'insumos.*.unidad_medida' => 'nullable|string|max:50',
            ]);

            $preparadoEnCocina = $request->has('preparado_en_cocina') && $request->preparado_en_cocina == '1';
            $esCompuesto = $request->has('es_compuesto') && $request->es_compuesto == '1';

            $producto = Producto::findOrFail($id);

            // Calcular costo de producción si es compuesto
            $costoProduccion = 0;
            if ($esCompuesto && isset($validated['insumos'])) {
                foreach ($validated['insumos'] as $insumoData) {
                    $insumo = Insumo::findOrFail($insumoData['insumo_id']);
                    $costoProduccion += $insumo->costo_unitario * $insumoData['cantidad_requerida'];
                }
            }

            $producto->update([
                'nombre' => $validated['nombre'],
                'codigo' => $validated['codigo'],
                'descripcion' => $validated['descripcion'] ?? null,
                'precio_venta' => $validated['precio_venta'],
                'costo_produccion' => $costoProduccion,
                'categoria' => $validated['categoria'] ?? null,
                'imagen_url' => $validated['imagen_url'] ?? null,
                'preparado_en_cocina' => $preparadoEnCocina,
                'tiempo_preparacion_minutos' => $validated['tiempo_preparacion_minutos'] ?? null,
            ]);

            // Eliminar insumos existentes y agregar nuevos
            ProductoInsumo::where('producto_id', $producto->id)->delete();

            if ($esCompuesto && isset($validated['insumos'])) {
                foreach ($validated['insumos'] as $insumoData) {
                    // Obtener unidad_medida del insumo si no se proporcionó
                    $unidadMedida = $insumoData['unidad_medida'] ?? null;
                    if (! $unidadMedida) {
                        $insumo = Insumo::find($insumoData['insumo_id']);
                        $unidadMedida = $insumo ? $insumo->unidad_medida : 'g';
                    }

                    ProductoInsumo::create([
                        'producto_id' => $producto->id,
                        'insumo_id' => $insumoData['insumo_id'],
                        'cantidad_requerida' => $insumoData['cantidad_requerida'],
                        'unidad_medida' => $unidadMedida,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado exitosamente.',
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar producto.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $producto = Producto::findOrFail($id);
            $producto->delete();

            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado exitosamente.',
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar producto.',
            ], 500);
        }
    }

    public function show($id)
    {
        $producto = Producto::with('insumos')->findOrFail($id);

        // Return JSON for AJAX requests
        if (request()->expectsJson()) {
            return response()->json($producto);
        }

        return view('productos.show', compact('producto'));
    }

    public function getInsumos()
    {
        $insumos = Insumo::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo', 'unidad_medida', 'costo_unitario']);

        return response()->json($insumos);
    }
}
