<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaLibroContable;
use App\Models\LibroContable;
use App\Models\Turno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TurnoController extends Controller
{
    public function abrirTurno(Request $request): JsonResponse
    {
        try {
            Log::info('abrirTurno llamado', ['user_id' => Auth::id()]);

            $validated = $request->validate([
                'monto_apertura' => 'nullable|numeric|min:0',
                'notas' => 'nullable|string',
            ]);

            Log::info('Validación pasada', $validated);

            // Verificar si el usuario ya tiene un turno abierto
            $turnoAbierto = Turno::where('user_id', Auth::id())
                ->where('estado', 'abierto')
                ->first();

            if ($turnoAbierto) {
                Log::info('Ya existe turno abierto', ['turno_id' => $turnoAbierto->id]);

                return response()->json([
                    'message' => 'Ya tienes un turno abierto',
                    'data' => $turnoAbierto,
                ], 400);
            }

            $user = Auth::user();
            $codigo = Turno::generarCodigo($user);

            Log::info('Creando turno', ['user_id' => $user->id, 'codigo' => $codigo]);

            $turno = Turno::create([
                'codigo' => $codigo,
                'user_id' => $user->id,
                'usuario_nombre' => $user->nombre.' '.$user->apellido,
                'fecha' => now()->toDateString(),
                'hora_apertura' => now(),
                'estado' => 'abierto',
                'monto_apertura' => $validated['monto_apertura'] ?? 0,
                'notas' => $validated['notas'] ?? null,
            ]);

            Log::info('Turno creado exitosamente', ['turno_id' => $turno->id]);

            return response()->json([
                'message' => 'Turno abierto exitosamente',
                'data' => $turno,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al abrir turno', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Error al abrir turno: '.$e->getMessage(),
            ], 500);
        }
    }

    public function cerrarTurno(Request $request): JsonResponse
    {
        try {
            Log::info('cerrarTurno llamado', ['user_id' => Auth::id()]);

            DB::beginTransaction();

            $validated = $request->validate([
                'notas_cierre' => 'nullable|string',
            ]);

            // Buscar turno abierto del usuario
            $turno = Turno::where('user_id', Auth::id())
                ->where('estado', 'abierto')
                ->first();

            if (! $turno) {
                Log::error('No hay turno abierto', ['user_id' => Auth::id()]);

                return response()->json([
                    'message' => 'No tienes un turno abierto',
                ], 400);
            }

            Log::info('Turno encontrado', ['turno_id' => $turno->id]);

            // Cerrar turno y calcular totales
            $turno->cerrar();

            Log::info('Turno cerrado', [
                'turno_id' => $turno->id,
                'total_ventas' => $turno->total_ventas,
                'numero_ordenes' => $turno->numero_ordenes,
            ]);

            if ($validated['notas_cierre'] ?? null) {
                $turno->notas = $validated['notas_cierre'];
                $turno->save();
            }

            // Registrar en libro contable si hay ventas
            if ($turno->total_ventas > 0) {
                Log::info('Registrando en libro contable', ['total_ventas' => $turno->total_ventas]);

                // Buscar o crear categoría "Ventas"
                $categoriaVentas = CategoriaLibroContable::where('nombre', 'Ventas')->first();

                if (! $categoriaVentas) {
                    Log::error('Categoría Ventas no encontrada');
                }

                LibroContable::create([
                    'fecha' => now()->toDateString(),
                    'tipo_movimiento' => 'entrada',
                    'monto' => $turno->total_ventas,
                    'descripcion' => $turno->getDescripcionContable(),
                    'saldo_despues' => $this->calcularSaldoNuevo($turno->total_ventas),
                    'categoria_id' => $categoriaVentas ? $categoriaVentas->id : null,
                    'referencia' => 'turno_'.$turno->id,
                    'user_id' => Auth::id(),
                ]);

                Log::info('Entrada en libro contable creada');
            } else {
                Log::info('No hay ventas para registrar en libro contable', ['total_ventas' => $turno->total_ventas]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Turno cerrado exitosamente',
                'data' => $turno->load('ordens'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cerrar turno', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Error al cerrar turno: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getTurnoActivo(): JsonResponse
    {
        try {
            $turno = Turno::where('user_id', Auth::id())
                ->where('estado', 'abierto')
                ->with('ordens')
                ->first();

            if (! $turno) {
                return response()->json([
                    'message' => 'No hay turno activo',
                    'data' => null,
                ]);
            }

            return response()->json([
                'data' => $turno,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener turno activo: '.$e->getMessage(),
            ], 500);
        }
    }

    public function index(): JsonResponse
    {
        try {
            $turnos = Turno::where('user_id', Auth::id())
                ->with('ordens')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'data' => $turnos,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener turnos: '.$e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $turno = Turno::with(['ordens', 'user'])
                ->findOrFail($id);

            return response()->json([
                'data' => $turno,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener turno: '.$e->getMessage(),
            ], 500);
        }
    }

    private function calcularSaldoNuevo(float $montoEntrada): float
    {
        $ultimoMovimiento = LibroContable::orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->first();
        $saldoActual = $ultimoMovimiento ? $ultimoMovimiento->saldo_despues : 0;

        return $saldoActual + $montoEntrada;
    }
}
