<?php

namespace App\Http\Controllers;

use App\Models\BitacoraAccion;
use App\Models\Configuracion;
use App\Models\DetalleOrden;
use App\Models\Orden;
use App\Models\Producto;
use App\Models\Turno;
use App\Services\InventarioService;
use App\Services\ReciboPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReciboController extends Controller
{
    public function index(Request $request)
    {
        $query = Orden::with(['user', 'cliente', 'detalles.producto']);

        if ($request->input('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->input('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $ordens = $query->orderBy('fecha_hora', 'desc')->paginate(20);

        // Guardar filtros para PDF
        $filtros = [
            'tipo' => $request->input('tipo'),
            'estado' => $request->input('estado'),
        ];

        // KPIs
        $totalRecibos = Orden::count();
        $ventasActivas = Orden::where('tipo', 'venta')->where('estado', 'cerrada')->count();
        $reembolsos = Orden::where('tipo', 'reembolso')->count();

        return view('recibos.index', compact(
            'ordens',
            'totalRecibos',
            'ventasActivas',
            'reembolsos',
            'filtros'
        ));
    }

    public function pdf(Request $request)
    {
        $query = Orden::with(['user', 'cliente', 'detalles.producto']);

        if ($request->input('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->input('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $ordens = $query->orderBy('fecha_hora', 'desc')->get();

        if ($ordens->isEmpty()) {
            return back()->with('error', 'No hay recibos para generar el PDF con los filtros actuales.');
        }

        $filtros = [
            'tipo' => $request->input('tipo'),
            'estado' => $request->input('estado'),
        ];

        $pdfService = new ReciboPdfService;

        return $pdfService->generarRecibos($ordens, $filtros);
    }

    public function show($id)
    {
        $orden = Orden::with(['user', 'cliente', 'detalles.producto'])->findOrFail($id);

        return view('recibos.show', compact('orden'));
    }

    public function pdfSingle($id)
    {
        $orden = Orden::with(['user', 'cliente', 'detalles.producto'])->findOrFail($id);

        $pdfService = new ReciboPdfService;

        return $pdfService->generarRecibo($orden);
    }

    public function anular(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            // lockForUpdate evita que dos peticiones simultáneas repongan el stock dos veces
            $orden = Orden::with('detalles.producto')->lockForUpdate()->findOrFail($id);

            // Verificar que no esté ya anulada
            if ($orden->estado === 'anulada') {
                DB::rollBack();

                return redirect()->back()->with('error', 'Este recibo ya está anulado.');
            }

            // Verificar que sea una venta (no un reembolso)
            if ($orden->tipo !== 'venta') {
                DB::rollBack();

                return redirect()->back()->with('error', 'Solo se pueden anular recibos de venta.');
            }

            // Si la venta había descontado stock, se devuelve (combos incluidos)
            $repuesto = app(InventarioService::class)->reponerVenta($orden);

            // Marcar la orden como anulada
            $orden->estado = 'anulada';
            $orden->save();

            BitacoraAccion::registrar(
                'recibo_anulado',
                'Recibo '.$orden->numero_recibo.' anulado (USD '.number_format((float) $orden->total, 2).')',
                'orden',
                $orden->id,
                null,
                ['numero_recibo' => $orden->numero_recibo, 'total' => (float) $orden->total, 'stock_repuesto' => $repuesto],
            );

            DB::commit();

            return redirect()->back()->with('success', 'Recibo anulado correctamente. Stock devuelto al inventario.');

        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return redirect()->back()->with('error', 'Error al anular recibo.');
        }
    }

    public function createReembolso(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $ordenOriginal = Orden::with('detalles.producto')->lockForUpdate()->findOrFail($id);

            // Un recibo solo puede reembolsarse una vez y solo si es una venta:
            // sin esta guarda se podía reembolsar N veces (devolviendo stock y dinero de más).
            if ($ordenOriginal->tipo !== 'venta') {
                DB::rollBack();

                return redirect()->back()->with('error', 'Solo se pueden reembolsar recibos de venta.');
            }

            if ($ordenOriginal->estado === 'anulada') {
                DB::rollBack();

                return redirect()->back()->with('error', 'Este recibo ya fue anulado o reembolsado.');
            }

            // Obtener tasa de cambio
            $tasaCambio = Configuracion::getTasaCambio();

            // Crear orden de reembolso
            $reembolso = Orden::create([
                'numero_recibo' => $this->generarNumeroRecibo('R'),
                'user_id' => Auth::id(),
                'cliente_id' => $ordenOriginal->cliente_id,
                'cliente_nombre' => $ordenOriginal->cliente_nombre,
                'tipo' => 'reembolso',
                'subtotal' => -$ordenOriginal->subtotal,
                'descuento' => -$ordenOriginal->descuento,
                'impuesto' => -$ordenOriginal->impuesto,
                'total' => -$ordenOriginal->total,
                'tasa_cambio' => $tasaCambio,
                'costo_insumos' => -$ordenOriginal->costo_insumos,
                'beneficio_bruto' => -$ordenOriginal->beneficio_bruto,
                'estado' => 'completada',
                'metodo_pago' => $ordenOriginal->metodo_pago,
                'turno' => $ordenOriginal->turno,
                'fecha_hora' => now(),
                'notas' => 'Reembolso de recibo #'.$ordenOriginal->numero_recibo,
                'recibo_anulado_id' => $ordenOriginal->numero_recibo,
            ]);

            // Crear detalles del reembolso
            foreach ($ordenOriginal->detalles as $detalle) {
                DetalleOrden::create([
                    'orden_id' => $reembolso->id,
                    'producto_id' => $detalle->producto_id,
                    'cantidad' => $detalle->cantidad,
                    'precio_unitario' => -$detalle->precio_unitario,
                    'subtotal' => -$detalle->subtotal,
                    'descuento' => -$detalle->descuento,
                    'total' => -$detalle->total,
                    'notas' => 'Reembolso: '.$detalle->notas,
                ]);
            }

            // Devolver stock (combos incluidos) y marcar el original como anulado
            $repuesto = app(InventarioService::class)->reponerVenta($ordenOriginal);
            $ordenOriginal->estado = 'anulada';
            $ordenOriginal->save();

            BitacoraAccion::registrar(
                'recibo_reembolsado',
                'Recibo '.$ordenOriginal->numero_recibo.' reembolsado con '.$reembolso->numero_recibo.' (USD '.number_format((float) $ordenOriginal->total, 2).')',
                'orden',
                $ordenOriginal->id,
                null,
                ['numero_recibo' => $ordenOriginal->numero_recibo, 'reembolso' => $reembolso->numero_recibo, 'stock_repuesto' => $repuesto],
            );

            DB::commit();

            return redirect()->back()->with('success', 'Reembolso creado correctamente. Stock revertido.');

        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return redirect()->back()->with('error', 'Error al crear reembolso.');
        }
    }

    /**
     * Elimina un recibo (solo administradores). Si era una venta que había descontado inventario,
     * lo devuelve; recalcula el turno si ya estaba cerrado y deja todo anotado en la bitácora
     * de acciones con el motivo y una copia de lo que tenía el recibo.
     */
    public function destroy(Request $request, $id)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $validated = $request->validate([
            'motivo' => 'required|string|min:3|max:500',
        ]);

        try {
            DB::beginTransaction();

            $orden = Orden::with(['detalles.producto', 'user', 'cliente'])->lockForUpdate()->findOrFail($id);

            if ($orden->tipo !== 'venta') {
                DB::rollBack();

                return redirect()->back()->with('error', 'Solo se pueden eliminar recibos de venta. Un reembolso no se elimina.');
            }

            $copia = [
                'numero_recibo' => $orden->numero_recibo,
                'estado' => $orden->estado,
                'total_usd' => (float) $orden->total,
                'tasa_cambio' => (float) $orden->tasa_cambio,
                'metodo_pago' => $orden->metodo_pago,
                'referencia_pago' => $orden->referencia_pago,
                'cliente' => $orden->cliente_nombre ?? $orden->cliente?->nombre,
                'cajero' => $orden->user?->name,
                'turno_id' => $orden->turno_id,
                'fecha_hora' => optional($orden->fecha_hora)->toDateTimeString(),
                'productos' => $orden->detalles->map(fn ($d) => [
                    'producto' => $d->producto_nombre ?? $d->producto?->nombre,
                    'cantidad' => (float) $d->cantidad,
                    'subtotal' => (float) $d->subtotal,
                ])->values()->all(),
            ];

            // Si ya estaba anulada, el stock se devolvió entonces; si no, se devuelve ahora
            $repuesto = app(InventarioService::class)->reponerVenta($orden);
            $copia['stock_repuesto'] = $repuesto;

            $turno = $orden->turno_id ? Turno::find($orden->turno_id) : null;
            $orden->delete();

            // Un turno cerrado conserva sus totales: se recalculan sin esta venta
            $turnoAjustado = false;
            if ($turno && $turno->isCerrado()) {
                $turno->total_ventas = $turno->ordens()->where('estado', 'cerrada')->sum('total');
                $turno->numero_ordenes = $turno->ordens()->where('estado', 'cerrada')->count();
                $turno->save();
                $turnoAjustado = true;
            }
            $copia['turno_recalculado'] = $turnoAjustado;

            BitacoraAccion::registrar(
                'recibo_eliminado',
                'Recibo '.$copia['numero_recibo'].' eliminado (USD '.number_format($copia['total_usd'], 2).', '.$orden->estado.')',
                'orden',
                $orden->id,
                $validated['motivo'],
                $copia,
            );

            DB::commit();

            $aviso = 'Recibo '.$copia['numero_recibo'].' eliminado y registrado en la bitácora.';
            if ($repuesto) {
                $aviso .= ' El inventario se devolvió.';
            }
            if ($turnoAjustado) {
                $aviso .= ' El turno ya estaba cerrado: se recalcularon sus totales, pero el libro contable no se modifica (regístralo a mano si hace falta).';
            }

            return redirect()->route('recibos.index')->with('success', $aviso);
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return redirect()->back()->with('error', 'No se pudo eliminar el recibo.');
        }
    }

    private function generarNumeroRecibo($prefijo = 'V')
    {
        $ultimoRecibo = Orden::where('numero_recibo', 'like', $prefijo.'%')
            ->orderBy('id', 'desc')
            ->first();

        if ($ultimoRecibo) {
            $ultimoNumero = intval(substr($ultimoRecibo->numero_recibo, 1));
            $nuevoNumero = $ultimoNumero + 1;
        } else {
            $nuevoNumero = 1;
        }

        return $prefijo.str_pad($nuevoNumero, 8, '0', STR_PAD_LEFT);
    }
}
