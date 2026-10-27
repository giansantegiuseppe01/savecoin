<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Insumo;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Inventario desde la app móvil (solo administradores): lista de insumos y carga/descarga de stock.
 */
class AdminInventarioController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Insumo::where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo', 'stock_actual', 'stock_minimo', 'unidad_medida'])
        );
    }

    public function movimiento(Request $request, InventarioService $inventario): JsonResponse
    {
        $validated = $request->validate([
            'insumo_id' => 'required|exists:insumos,id',
            'tipo_movimiento' => 'required|in:entrada,salida',
            'cantidad' => 'required|numeric|min:0.0001',
            'motivo' => 'required|string|max:500',
        ]);

        try {
            $insumo = DB::transaction(function () use ($validated, $inventario) {
                // Bloqueo de la fila: dos movimientos simultáneos no se pisan
                $insumo = Insumo::whereKey($validated['insumo_id'])->lockForUpdate()->firstOrFail();

                return $inventario->registrarMovimiento(
                    $insumo,
                    $validated['tipo_movimiento'],
                    (float) $validated['cantidad'],
                    $validated['motivo'],
                    Auth::id(),
                );
            });
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Stock actualizado correctamente.',
            'data' => $insumo->only(['id', 'nombre', 'codigo', 'stock_actual', 'stock_minimo', 'unidad_medida']),
        ]);
    }
}
