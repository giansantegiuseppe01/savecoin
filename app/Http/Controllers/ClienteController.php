<?php

namespace App\Http\Controllers;

use App\Models\AbonoCredito;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Orden;
use App\Models\Turno;
use App\Models\VentaCredito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClienteController extends Controller
{
    /**
     * Cobro de una deuda a crédito. El monto se aplica a las ventas a crédito más antiguas primero
     * y entra a la caja del turno abierto del usuario.
     */
    public function abonar(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'forma_pago' => 'required|string|max:50',
            'referencia' => 'nullable|string|max:100',
        ]);

        $turno = Turno::where('user_id', Auth::id())->where('estado', 'abierto')->first();
        if (! $turno) {
            return response()->json(['success' => false, 'message' => 'Debes tener un turno abierto para registrar un cobro.'], 400);
        }

        try {
            DB::transaction(function () use ($cliente, $validated, $turno) {
                $pendientes = VentaCredito::pendientes()
                    ->where('ventas_credito.cliente_id', $cliente->id)
                    ->orderBy('ventas_credito.id')
                    ->lockForUpdate()
                    ->get();

                $deuda = round((float) $pendientes->sum('saldo'), 2);
                if ($pendientes->isEmpty()) {
                    throw ValidationException::withMessages(['monto' => 'Este cliente no tiene ventas a crédito pendientes.']);
                }
                if ((float) $validated['monto'] > $deuda + 0.001) {
                    throw ValidationException::withMessages(['monto' => 'El monto supera la deuda a crédito de $'.number_format($deuda, 2).'.']);
                }

                $tasa = (float) Configuracion::getTasaCambio();
                $restante = (float) $validated['monto'];

                foreach ($pendientes as $venta) {
                    if ($restante <= 0) {
                        break;
                    }
                    $aplicar = round(min($restante, (float) $venta->saldo), 2);

                    AbonoCredito::create([
                        'venta_credito_id' => $venta->id,
                        'turno_id' => $turno->id,
                        'user_id' => Auth::id(),
                        'monto' => $aplicar,
                        'tasa_cambio' => $tasa,
                        'forma_pago' => $validated['forma_pago'],
                        'referencia' => $validated['referencia'] ?? null,
                    ]);

                    $venta->saldo = round((float) $venta->saldo - $aplicar, 2);
                    if ($venta->saldo <= 0) {
                        $venta->saldo = 0;
                        $venta->estado = 'pagada';
                    }
                    $venta->save();

                    $restante = round($restante - $aplicar, 2);
                }
            });
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Cobro registrado exitosamente.']);
    }

    public function index(Request $request)
    {
        $abiertas = fn ($q) => $q->where('tipo', 'venta')->where('estado', 'abierta');
        $query = Cliente::query()
            ->withCount(['ordens as ordenes_total', 'ordens as tickets_abiertos' => $abiertas])
            ->withSum(['ordens as pagado_sum' => fn ($q) => $q->where('tipo', 'venta')->whereIn('estado', ['cerrada', 'completada'])], 'total')
            ->withSum(['ordens as deuda_sum' => $abiertas], 'total')
            ->withSum(['ventasCredito as credito_pendiente' => fn ($q) => $q->pendientes()], 'saldo');

        if ($request->boolean('con_deuda')) {
            $query->where(fn ($q) => $q->whereHas('ordens', $abiertas)
                ->orWhereHas('ventasCredito', fn ($c) => $c->pendientes()));
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('apellido', 'like', "%{$buscar}%")
                    ->orWhere('cedula', 'like', "%{$buscar}%")
                    ->orWhere('telefono', 'like', "%{$buscar}%")
                    ->orWhere('correo', 'like', "%{$buscar}%");
            });
        }

        // 1. Si la Petición es de la API (Flutter) o solicita JSON
        if ($request->wantsJson() || $request->is('api/*')) {
            $clientes = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'data' => $clientes,
            ]);
        }

        // 2. Si es navegación convencional desde la Web de Laravel
        $clientes = $query
            ->with(['ordens' => fn ($q) => $abiertas($q)->orderBy('fecha_hora')])
            ->tap(fn ($q) => $this->ordenar($q, $request, [
                'nombre' => \Illuminate\Support\Facades\DB::raw('LOWER(nombre)'),
                'cedula' => 'cedula',
                'telefono' => 'telefono',
                'correo' => \Illuminate\Support\Facades\DB::raw('LOWER(correo)'),
                'ordenes' => 'ordenes_total',
                'pagado' => 'pagado_sum',
                'debe' => 'deuda_sum',
            ], $request->boolean('con_deuda') ? [['deuda_sum', 'desc']] : [['created_at', 'desc']]))
            ->paginate(15)
            ->withQueryString();

        // Totales de todos los clientes (no solo de la página que se ve)
        $totalPagado = (float) Orden::where('tipo', 'venta')->whereIn('estado', ['cerrada', 'completada'])->whereNotNull('cliente_id')->sum('total');
        $porCobrar = Orden::where('tipo', 'venta')->where('estado', 'abierta')->whereNotNull('cliente_id');
        $totalPorCobrar = (float) (clone $porCobrar)->sum('total') + (float) VentaCredito::pendientes()->sum('saldo');
        $clientesQueDeben = Cliente::query()
            ->where(fn ($q) => $q->whereHas('ordens', fn ($o) => $o->where('tipo', 'venta')->where('estado', 'abierta'))
                ->orWhereHas('ventasCredito', fn ($c) => $c->pendientes()))
            ->count();
        $ticketsAbiertos = (clone $porCobrar)->count();

        return view('clientes.index', compact('clientes', 'totalPagado', 'totalPorCobrar', 'clientesQueDeben', 'ticketsAbiertos'));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'apellido' => 'required|string|max:255',
                'cedula' => 'required|string|max:50|unique:clientes',
                'telefono' => 'nullable|string|max:50',
                'correo' => 'nullable|email|max:255',
            ]);

            Cliente::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Cliente creado exitosamente.',
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
                'message' => 'Error al crear cliente.',
            ], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|exists:clientes',
                'nombre' => 'required|string|max:255',
                'apellido' => 'required|string|max:255',
                'cedula' => 'required|string|max:50|unique:clientes,cedula,'.$request->id,
                'telefono' => 'nullable|string|max:50',
                'correo' => 'nullable|email|max:255',
            ]);

            $cliente = Cliente::findOrFail($validated['id']);
            $cliente->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Cliente actualizado exitosamente.',
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
                'message' => 'Error al actualizar cliente.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $cliente = Cliente::findOrFail($id);

            if ($cliente->ordens()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el cliente porque tiene órdenes asociadas.',
                ], 400);
            }

            $cliente->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cliente eliminado exitosamente.',
            ]);

        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar cliente.',
            ], 500);
        }
    }

    public function estadisticas()
    {
        $clienteDelMes = Cliente::withCount('ordens')
            ->whereHas('ordens', function ($query) {
                $query->whereMonth('fecha_hora', now()->month)
                    ->whereYear('fecha_hora', now()->year);
            })
            ->orderBy('ordens_count', 'desc')
            ->first();

        $topClientes = Cliente::withCount('ordens')
            ->orderBy('ordens_count', 'desc')
            ->limit(10)
            ->get();

        return view('clientes.estadisticas', compact('clienteDelMes', 'topClientes'));
    }
}
