<?php

namespace App\Http\Controllers;

use App\Models\CategoriaLibroContable;
use App\Models\Configuracion;
use App\Models\CuentaPorPagar;
use App\Models\PagoPorPagar;
use App\Services\LibroContableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CuentaPorPagarController extends Controller
{
    public function index(Request $request): View
    {
        $query = CuentaPorPagar::query();

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('acreedor', 'like', "%{$buscar}%")
                    ->orWhere('concepto', 'like', "%{$buscar}%")
                    ->orWhere('numero_documento', 'like', "%{$buscar}%");
            });
        }

        if ($request->filled('tipo') && array_key_exists($request->tipo, CuentaPorPagar::TIPOS)) {
            $query->where('tipo', $request->tipo);
        }

        match ($request->input('estado')) {
            'pendiente' => $query->pendientes()->where(fn ($q) => $q->whereNull('fecha_vencimiento')->orWhereDate('fecha_vencimiento', '>=', now()->toDateString())),
            'vencida' => $query->vencidas(),
            'pagada' => $query->where('estado', 'pagada'),
            default => null,
        };

        $cuentas = $this->ordenar((clone $query)->with('pagos'), $request, [
            'acreedor' => DB::raw('LOWER(acreedor)'),
            'tipo' => 'tipo',
            'monto' => 'monto',
            'saldo' => 'saldo',
            'vence' => 'fecha_vencimiento',
            'estado' => 'estado',
        ], [[DB::raw("CASE WHEN estado = 'pendiente' THEN 0 ELSE 1 END"), 'asc'], ['fecha_vencimiento', 'asc'], ['id', 'desc']])
            ->paginate(20)
            ->withQueryString();

        // Totales de todas las cuentas (no solo de la página que se ve)
        $totalDebe = (float) CuentaPorPagar::pendientes()->sum('saldo');
        $cantidadVencidas = CuentaPorPagar::vencidas()->count();
        $montoVencido = (float) CuentaPorPagar::vencidas()->sum('saldo');

        // A quién se le debe: saldo pendiente agrupado por acreedor
        $porAcreedor = CuentaPorPagar::pendientes()
            ->select('acreedor', DB::raw('SUM(saldo) as saldo_total'), DB::raw('COUNT(*) as cuentas'))
            ->groupBy('acreedor')
            ->orderByDesc('saldo_total')
            ->get();

        return view('cuentas-por-pagar.index', compact(
            'cuentas', 'totalDebe', 'cantidadVencidas', 'montoVencido', 'porAcreedor',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validarCuenta($request);

        CuentaPorPagar::create($validated + [
            'saldo' => $validated['monto'],
            'estado' => 'pendiente',
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Cuenta por pagar registrada.');
    }

    public function update(Request $request, CuentaPorPagar $cuenta): RedirectResponse
    {
        $validated = $this->validarCuenta($request, $cuenta);

        // El monto solo se puede cambiar si aún no tiene pagos; así el saldo siempre cuadra
        if ($cuenta->pagos()->exists()) {
            $validated['monto'] = $cuenta->monto;
            $validated['saldo'] = $cuenta->saldo;
        } else {
            $validated['saldo'] = $validated['monto'];
        }

        $cuenta->update($validated);

        return back()->with('success', 'Cuenta por pagar actualizada.');
    }

    public function destroy(CuentaPorPagar $cuenta): RedirectResponse
    {
        if ($cuenta->pagos()->exists()) {
            return back()->withErrors(['eliminar' => 'No se puede eliminar una cuenta que ya tiene pagos registrados.']);
        }

        $cuenta->delete();

        return back()->with('success', 'Cuenta por pagar eliminada.');
    }

    public function pagar(Request $request, CuentaPorPagar $cuenta): RedirectResponse
    {
        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'forma_pago' => 'required|string|max:50',
            'referencia' => 'nullable|string|max:100',
            'fecha' => 'required|date',
        ]);

        try {
            DB::transaction(function () use ($cuenta, $validated) {
                $cuenta = CuentaPorPagar::whereKey($cuenta->id)->lockForUpdate()->firstOrFail();

                if ($cuenta->estado !== 'pendiente') {
                    throw new \RuntimeException('Esta cuenta ya está pagada.');
                }
                if ((float) $validated['monto'] > (float) $cuenta->saldo + 0.001) {
                    throw new \RuntimeException('El pago supera el saldo pendiente de $'.number_format((float) $cuenta->saldo, 2).'.');
                }

                PagoPorPagar::create([
                    'cuenta_por_pagar_id' => $cuenta->id,
                    'monto' => $validated['monto'],
                    'forma_pago' => $validated['forma_pago'],
                    'referencia' => $validated['referencia'] ?? null,
                    'fecha' => $validated['fecha'],
                    'user_id' => Auth::id(),
                ]);

                $nuevoSaldo = round((float) $cuenta->saldo - (float) $validated['monto'], 2);
                $cuenta->saldo = max($nuevoSaldo, 0);
                if ($cuenta->saldo <= 0) {
                    $cuenta->estado = 'pagada';
                }
                $cuenta->save();

                // Todo pago a proveedores o acreedores sale del libro contable como salida (en Bs)
                app(LibroContableService::class)->registrar([
                    'fecha' => $validated['fecha'],
                    'tipo_movimiento' => 'salida',
                    'monto' => round((float) $validated['monto'] * (float) Configuracion::getTasaCambio(), 4),
                    'descripcion' => mb_substr('Pago a '.$cuenta->acreedor
                        .($cuenta->concepto ? ' · '.$cuenta->concepto : '')
                        .($cuenta->numero_documento ? ' (Doc. '.$cuenta->numero_documento.')' : '')
                        .' · USD '.number_format((float) $validated['monto'], 2), 0, 500),
                    'referencia' => 'cxp_'.$cuenta->id.'_'.$validated['forma_pago'].(! empty($validated['referencia']) ? '_'.$validated['referencia'] : ''),
                    'categoria_id' => CategoriaLibroContable::firstOrCreate(['nombre' => 'Pagos a proveedores'])->id,
                ], Auth::id());
            });
        } catch (\RuntimeException|\DomainException $e) {
            return back()->withErrors(['pago' => $e->getMessage()]);
        }

        return back()->with('success', 'Pago registrado.');
    }

    /** @return array<string, mixed> */
    private function validarCuenta(Request $request, ?CuentaPorPagar $cuenta = null): array
    {
        return $request->validate([
            'acreedor' => 'required|string|max:150',
            'tipo' => ['required', Rule::in(array_keys(CuentaPorPagar::TIPOS))],
            'concepto' => 'nullable|string|max:255',
            'numero_documento' => 'nullable|string|max:100',
            'monto' => 'required|numeric|min:0.01',
            'fecha_emision' => 'required|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha_emision',
            'notas' => 'nullable|string',
        ]);
    }
}
