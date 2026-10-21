<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaLibroContable;
use App\Models\LibroContable;
use App\Models\Turno;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            report($e);
            Log::error('Error al abrir turno', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Error al abrir turno.',
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
                ->lockForUpdate()
                ->first();

            if (! $turno) {
                DB::rollBack();
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

                // El libro contable está en bolívares y total_ventas está en USD:
                // se convierte con la tasa de cada orden (antes se asentaba el monto en USD).
                $montoBs = $turno->totalVentasBs();

                LibroContable::create([
                    'fecha' => now()->toDateString(),
                    'tipo_movimiento' => 'entrada',
                    'monto' => $montoBs,
                    'descripcion' => $turno->getDescripcionContable().' (USD '.number_format((float) $turno->total_ventas, 2).')',
                    'saldo_despues' => $this->calcularSaldoNuevo($montoBs),
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
                'resumen' => $turno->resumenCierre(),
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();
            Log::error('Error al cerrar turno', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Error al cerrar turno.',
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
            report($e);

            return response()->json([
                'message' => 'Error al obtener turno activo.',
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
            report($e);

            return response()->json([
                'message' => 'Error al obtener turnos.',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $query = Turno::with(['ordens', 'user']);

            // Los empleados solo pueden consultar sus propios turnos; el admin ve todos.
            if (! Auth::user()->isAdmin()) {
                $query->where('user_id', Auth::id());
            }

            $turno = $query->findOrFail($id);

            return response()->json([
                'data' => $turno,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Turno no encontrado.',
            ], 404);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'message' => 'Error al obtener turno.',
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
