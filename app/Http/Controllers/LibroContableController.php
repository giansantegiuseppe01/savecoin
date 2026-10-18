<?php

namespace App\Http\Controllers;

use App\Models\CategoriaLibroContable;
use App\Models\LibroContable;
use App\Services\ReciboPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LibroContableController extends Controller
{
    public function index(Request $request)
    {
        $query = LibroContable::with(['user', 'categoria']);

        if ($request->input('fecha_inicio')) {
            $query->where('fecha', '>=', $request->input('fecha_inicio'));
        }

        if ($request->input('fecha_fin')) {
            $query->where('fecha', '<=', $request->input('fecha_fin'));
        }

        if ($request->input('tipo_movimiento')) {
            $query->where('tipo_movimiento', $request->input('tipo_movimiento'));
        }

        $movimientos = $query->orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->paginate(30);

        // Guardar filtros para PDF
        $filtros = [
            'fecha_inicio' => $request->input('fecha_inicio'),
            'fecha_fin' => $request->input('fecha_fin'),
            'tipo_movimiento' => $request->input('tipo_movimiento'),
        ];

        // Calcular saldo actual
        $saldoActual = $this->calcularSaldoActual();

        // KPIs
        $totalEntradas = LibroContable::where('tipo_movimiento', 'entrada')->sum('monto');
        $totalSalidas = LibroContable::where('tipo_movimiento', 'salida')->sum('monto');
        $totalMovimientos = LibroContable::count();

        // Cargar categorías activas
        $categorias = CategoriaLibroContable::where('activo', true)->orderBy('nombre')->get();

        return view('libro-contable.index', compact(
            'movimientos',
            'saldoActual',
            'totalEntradas',
            'totalSalidas',
            'totalMovimientos',
            'categorias',
            'filtros'
        ));
    }

    public function pdf(Request $request)
    {
        $query = LibroContable::with(['user', 'categoria']);

        if ($request->input('fecha_inicio')) {
            $query->where('fecha', '>=', $request->input('fecha_inicio'));
        }

        if ($request->input('fecha_fin')) {
            $query->where('fecha', '<=', $request->input('fecha_fin'));
        }

        if ($request->input('tipo_movimiento')) {
            $query->where('tipo_movimiento', $request->input('tipo_movimiento'));
        }

        $movimientos = $query->orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->get();

        if ($movimientos->isEmpty()) {
            return back()->with('error', 'No hay movimientos para generar el PDF con los filtros actuales.');
        }

        $filtros = [
            'fecha_inicio' => $request->input('fecha_inicio'),
            'fecha_fin' => $request->input('fecha_fin'),
            'tipo_movimiento' => $request->input('tipo_movimiento'),
        ];

        $pdfService = new ReciboPdfService;

        return $pdfService->generarLibroContable($movimientos, $filtros);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'fecha' => 'required|date',
                'tipo_movimiento' => 'required|in:entrada,salida',
                'monto' => 'required|numeric|min:0.0001',
                'descripcion' => 'required|string|max:500',
                'referencia' => 'nullable|string|max:100',
                'categoria_id' => 'nullable|exists:categoria_libro_contables,id',
            ]);

            // Calcular saldo ANTES de cualquier movimiento en esa fecha
            $saldoAnterior = $this->calcularSaldoAntesDeFecha($validated['fecha']);

            // Calcular saldo después
            $saldoDespues = $validated['tipo_movimiento'] === 'entrada'
                ? $saldoAnterior + $validated['monto']
                : $saldoAnterior - $validated['monto'];

            // Validar saldo suficiente para salidas
            if ($validated['tipo_movimiento'] === 'salida' && $saldoDespues < 0) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Saldo insuficiente para realizar esta salida.',
                ], 400);
            }

            $movimiento = LibroContable::create([
                'fecha' => $validated['fecha'],
                'tipo_movimiento' => $validated['tipo_movimiento'],
                'monto' => $validated['monto'],
                'descripcion' => $validated['descripcion'],
                'saldo_despues' => $saldoDespues,
                'referencia' => $validated['referencia'] ?? null,
                'categoria_id' => $validated['categoria_id'] ?? null,
                'user_id' => Auth::id(),
            ]);

            // Recalcular TODOS los saldos en orden cronológico
            $this->recalcularTodosSaldos();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Movimiento registrado exitosamente.',
                'saldo_actual' => $this->calcularSaldoActual(),
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
                'message' => 'Error al registrar movimiento.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $movimiento = LibroContable::findOrFail($id);
            $fecha = $movimiento->fecha;
            $movimiento->delete();

            // Recalcular saldos posteriores
            $this->recalcularSaldosPosteriores($fecha);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Movimiento eliminado exitosamente.',
                'saldo_actual' => $this->calcularSaldoActual(),
            ]);
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar movimiento.',
            ], 500);
        }
    }

    private function calcularSaldoActual(): float
    {
        $ultimoMovimiento = LibroContable::orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->first();

        return $ultimoMovimiento ? $ultimoMovimiento->saldo_despues : 0;
    }

    private function calcularSaldoAntesDeFecha($fecha): float
    {
        // Calcular saldo total de todos los movimientos ANTES de esa fecha
        $saldo = LibroContable::where('fecha', '<', $fecha)
            ->get()
            ->reduce(function ($carry, $movimiento) {
                if ($movimiento->tipo_movimiento === 'entrada') {
                    return $carry + $movimiento->monto;
                } else {
                    return $carry - $movimiento->monto;
                }
            }, 0);

        return $saldo;
    }

    private function recalcularTodosSaldos()
    {
        // Obtener todos los movimientos en orden cronológico
        $todosMovimientos = LibroContable::orderBy('fecha')->orderBy('created_at')->get();

        $saldo = 0;

        foreach ($todosMovimientos as $movimiento) {
            if ($movimiento->tipo_movimiento === 'entrada') {
                $saldo += $movimiento->monto;
            } else {
                $saldo -= $movimiento->monto;
            }
            $movimiento->saldo_despues = $saldo;
            $movimiento->save();
        }
    }
}
