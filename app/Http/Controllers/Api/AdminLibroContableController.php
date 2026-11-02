<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaLibroContable;
use App\Models\LibroContable;
use App\Services\LibroContableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Libro contable desde la app móvil (solo administradores): consulta y registro de entradas/salidas.
 * Los montos están en bolívares, igual que en la web.
 */
class AdminLibroContableController extends Controller
{
    public function index(Request $request, LibroContableService $libro): JsonResponse
    {
        $limite = min(max((int) $request->query('limite', 30), 1), 100);

        $movimientos = LibroContable::with(['categoria', 'user'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit($limite)
            ->get()
            ->map(fn (LibroContable $m) => $this->formatear($m));

        return response()->json([
            'saldo_actual' => $libro->saldoActual(),
            'total_entradas' => (float) LibroContable::where('tipo_movimiento', 'entrada')->sum('monto'),
            'total_salidas' => (float) LibroContable::where('tipo_movimiento', 'salida')->sum('monto'),
            'movimientos' => $movimientos,
            'categorias' => CategoriaLibroContable::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request, LibroContableService $libro): JsonResponse
    {
        $validated = $request->validate([
            'fecha' => 'required|date_format:Y-m-d|before_or_equal:today',
            'tipo_movimiento' => 'required|in:entrada,salida',
            'monto' => 'required|numeric|min:0.01|max:999999999999',
            'descripcion' => 'required|string|max:500',
            'referencia' => 'nullable|string|max:100',
            'categoria_id' => 'nullable|exists:categoria_libro_contables,id',
        ]);

        try {
            $movimiento = $libro->registrar($validated, Auth::id());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Movimiento registrado.',
            'data' => $this->formatear($movimiento),
            'saldo_actual' => $libro->saldoActual(),
        ], 201);
    }

    private function formatear(LibroContable $m): array
    {
        return [
            'id' => $m->id,
            'fecha' => $m->fecha->format('Y-m-d'),
            'tipo_movimiento' => $m->tipo_movimiento,
            'monto' => (float) $m->monto,
            'descripcion' => $m->descripcion,
            'referencia' => $m->referencia,
            'categoria' => $m->categoria?->nombre,
            'saldo_despues' => (float) $m->saldo_despues,
            'usuario' => $m->user ? trim($m->user->nombre.' '.$m->user->apellido) : null,
        ];
    }
}
