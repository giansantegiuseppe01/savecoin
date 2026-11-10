<?php

namespace App\Http\Controllers;

use App\Models\BitacoraInventario;
use App\Models\Insumo;
use App\Services\InventarioService;
use App\Services\ReciboPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioController extends Controller
{
    public function index(Request $request)
    {
        $query = Insumo::query();

        if ($request->input('buscar')) {
            $buscar = $request->input('buscar');
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', '%'.$buscar.'%')
                    ->orWhere('codigo', 'like', '%'.$buscar.'%');
            });
        }

        if ($request->input('estado') === 'activo') {
            $query->where('activo', true);
        } elseif ($request->input('estado') === 'inactivo') {
            $query->where('activo', false);
        }

        $insumos = $query->with('ingredientes')->orderBy('nombre')->paginate(20);

        // Guardar filtros para PDF
        $filtros = [
            'buscar' => $request->input('buscar'),
            'estado' => $request->input('estado'),
        ];

        // KPIs
        $totalInsumos = Insumo::count();
        $stockBajo = Insumo::whereColumn('stock_actual', '<', 'stock_minimo')->count();
        $insumosActivos = Insumo::where('activo', true)->count();

        $todosInsumos = Insumo::orderBy('nombre')->get(['id', 'nombre', 'unidad_medida']);

        return view('inventario.index', compact(
            'todosInsumos',
            'insumos',
            'totalInsumos',
            'stockBajo',
            'insumosActivos',
            'filtros'
        ));
    }

    public function pdf(Request $request)
    {
        $query = Insumo::query();

        if ($request->input('buscar')) {
            $buscar = $request->input('buscar');
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', '%'.$buscar.'%')
                    ->orWhere('codigo', 'like', '%'.$buscar.'%');
            });
        }

        if ($request->input('estado') === 'activo') {
            $query->where('activo', true);
        } elseif ($request->input('estado') === 'inactivo') {
            $query->where('activo', false);
        }

        $insumos = $query->orderBy('nombre')->get();

        if ($insumos->isEmpty()) {
            return back()->with('error', 'No hay insumos para generar el PDF con los filtros actuales.');
        }

        $filtros = [
            'buscar' => $request->input('buscar'),
            'estado' => $request->input('estado'),
        ];

        $pdfService = new ReciboPdfService;

        return $pdfService->generarInventario($insumos, $filtros);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'codigo' => 'required|string|max:50|unique:insumos',
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:500',
                'unidad_medida' => 'required|in:G,Kg,Lt,Unidad',
                // Un insumo preparado calcula su costo desde la receta; solo los simples lo piden
                'costo_unitario' => 'required_without:ingredientes|nullable|numeric|min:0',
                'stock_actual' => 'required|numeric|min:0',
                'stock_minimo' => 'required|numeric|min:0',
                'rendimiento' => 'nullable|numeric|gt:0',
                'ingredientes' => 'nullable|array',
                'ingredientes.*.insumo_id' => 'required_with:ingredientes|exists:insumos,id',
                'ingredientes.*.cantidad' => 'required_with:ingredientes|numeric|gt:0',
            ]);

            $activo = $request->has('activo') && $request->activo == '1';

            $insumo = Insumo::create([
                'codigo' => $validated['codigo'],
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'unidad_medida' => $validated['unidad_medida'],
                'costo_unitario' => $validated['costo_unitario'] ?? 0,
                'stock_actual' => $validated['stock_actual'],
                'stock_minimo' => $validated['stock_minimo'],
                'activo' => $activo,
            ]);

            $this->guardarReceta($insumo, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Insumo creado exitosamente.',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear insumo.',
            ], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|exists:insumos',
                'codigo' => 'required|string|max:50|unique:insumos,codigo,'.$request->id,
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:500',
                'unidad_medida' => 'required|in:G,Kg,Lt,Unidad',
                // Un insumo preparado calcula su costo desde la receta; solo los simples lo piden
                'costo_unitario' => 'required_without:ingredientes|nullable|numeric|min:0',
                'stock_minimo' => 'required|numeric|min:0',
                'rendimiento' => 'nullable|numeric|gt:0',
                'ingredientes' => 'nullable|array',
                'ingredientes.*.insumo_id' => 'required_with:ingredientes|exists:insumos,id',
                'ingredientes.*.cantidad' => 'required_with:ingredientes|numeric|gt:0',
            ]);

            $activo = $request->has('activo') && $request->activo == '1';

            $insumo = Insumo::findOrFail($validated['id']);
            $insumo->update([
                'codigo' => $validated['codigo'],
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'unidad_medida' => $validated['unidad_medida'],
                'costo_unitario' => $validated['costo_unitario'] ?? 0,
                'stock_minimo' => $validated['stock_minimo'],
                'activo' => $activo,
            ]);

            $this->guardarReceta($insumo, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Insumo actualizado exitosamente.',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar insumo.',
            ], 500);
        }
    }

    /** Guarda (o quita) la receta de un insumo preparado a partir de los datos validados. */
    private function guardarReceta(Insumo $insumo, array $validated): void
    {
        $filas = $validated['ingredientes'] ?? [];
        $rendimiento = isset($validated['rendimiento']) ? (float) $validated['rendimiento'] : null;

        if ($filas !== [] && $rendimiento === null) {
            // Sin rendimiento indicado, rinde lo que suman los ingredientes
            $rendimiento = (float) collect($filas)->sum('cantidad');
        }

        $insumo->guardarReceta($rendimiento, $filas);
    }

    public function destroy($id)
    {
        try {
            $insumo = Insumo::findOrFail($id);

            // Verificar si el insumo está siendo usado en productos
            if ($insumo->productos()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el insumo porque está siendo usado en productos.',
                ], 400);
            }

            $insumo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Insumo eliminado exitosamente.',
            ]);

        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar insumo.',
            ], 500);
        }
    }

    public function agregarStock(Request $request)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'insumo_id' => 'required|exists:insumos,id',
                'cantidad' => 'required|numeric|min:0.0001',
                'tipo_movimiento' => 'required|in:entrada,salida,ajuste',
                'motivo' => 'required|string|max:500',
            ]);

            $insumo = Insumo::findOrFail($validated['insumo_id']);
            $cantidad = $validated['cantidad'];
            $tipoMovimiento = $validated['tipo_movimiento'];

            $stockAnterior = $insumo->stock_actual;

            // Cargar un insumo preparado consume sus ingredientes (y queda en la bitácora)
            if ($tipoMovimiento === 'entrada' && $insumo->esPreparado()) {
                try {
                    $insumo = app(InventarioService::class)
                        ->registrarMovimiento($insumo, 'entrada', (float) $cantidad, $validated['motivo'], Auth::id());
                } catch (\DomainException $e) {
                    DB::rollBack();

                    return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
                }
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Preparación registrada: se descontaron los ingredientes.',
                    'stock_actual' => $insumo->stock_actual,
                ]);
            }

            if ($tipoMovimiento === 'entrada') {
                $insumo->stock_actual += $cantidad;
            } elseif ($tipoMovimiento === 'salida') {
                if ($insumo->stock_actual < $cantidad) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Stock insuficiente para realizar esta salida.',
                    ], 400);
                }
                $insumo->stock_actual -= $cantidad;
            } elseif ($tipoMovimiento === 'ajuste') {
                $insumo->stock_actual = $cantidad;
            }

            $insumo->save();

            // Registrar en bitácora
            BitacoraInventario::create([
                'insumo_id' => $insumo->id,
                'tipo_movimiento' => $tipoMovimiento,
                'cantidad_anterior' => $stockAnterior,
                'cantidad_movimiento' => $cantidad,
                'cantidad_nueva' => $insumo->stock_actual,
                'motivo' => $validated['motivo'],
                'user_id' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock actualizado correctamente.',
                'stock_actual' => $insumo->stock_actual,
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
                'message' => 'Error al actualizar stock.',
            ], 500);
        }
    }

    public function bitacora(Request $request)
    {
        // Solo movimientos manuales: las ventas descuentan stock sin pasar por la bitácora
        $query = BitacoraInventario::with(['insumo', 'user'])->whereNull('orden_id');

        if ($request->input('insumo_id')) {
            $query->where('insumo_id', $request->input('insumo_id'));
        }

        if ($request->input('tipo_movimiento')) {
            $query->where('tipo_movimiento', $request->input('tipo_movimiento'));
        }

        if ($request->input('fecha_inicio')) {
            $query->where('created_at', '>=', $request->input('fecha_inicio'));
        }

        if ($request->input('fecha_fin')) {
            $query->where('created_at', '<=', $request->input('fecha_fin'));
        }

        $movimientos = $query->orderBy('created_at', 'desc')->paginate(30);

        $insumos = Insumo::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id');

        // Guardar filtros para PDF
        $filtros = [
            'insumo_id' => $request->input('insumo_id'),
            'tipo_movimiento' => $request->input('tipo_movimiento'),
            'fecha_inicio' => $request->input('fecha_inicio'),
            'fecha_fin' => $request->input('fecha_fin'),
        ];

        // Acciones sensibles (recibos eliminados, anulados, reembolsados...)
        $acciones = \App\Models\BitacoraAccion::with('user')->orderByDesc('id')->paginate(15, ['*'], 'acciones');

        return view('inventario.bitacora', compact('movimientos', 'insumos', 'filtros', 'acciones'));
    }

    public function bitacoraPdf(Request $request)
    {
        // Solo movimientos manuales: las ventas descuentan stock sin pasar por la bitácora
        $query = BitacoraInventario::with(['insumo', 'user'])->whereNull('orden_id');

        if ($request->input('insumo_id')) {
            $query->where('insumo_id', $request->input('insumo_id'));
        }

        if ($request->input('tipo_movimiento')) {
            $query->where('tipo_movimiento', $request->input('tipo_movimiento'));
        }

        if ($request->input('fecha_inicio')) {
            $query->where('created_at', '>=', $request->input('fecha_inicio'));
        }

        if ($request->input('fecha_fin')) {
            $query->where('created_at', '<=', $request->input('fecha_fin'));
        }

        $movimientos = $query->orderBy('created_at', 'desc')->get();

        if ($movimientos->isEmpty()) {
            return back()->with('error', 'No hay movimientos en la bitácora para generar el PDF con los filtros actuales.');
        }

        $filtros = [
            'insumo_id' => $request->input('insumo_id'),
            'tipo_movimiento' => $request->input('tipo_movimiento'),
            'fecha_inicio' => $request->input('fecha_inicio'),
            'fecha_fin' => $request->input('fecha_fin'),
        ];

        $pdfService = new ReciboPdfService;

        return $pdfService->generarBitacora($movimientos, $filtros);
    }
}
