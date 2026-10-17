<?php

namespace App\Http\Controllers;

use App\Models\BitacoraInventario;
use App\Models\Configuracion;
use App\Models\DetalleOrden;
use App\Models\Orden;
use App\Models\Producto;
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
        $ventasActivas = Orden::where('tipo', 'venta')->where('estado', 'completada')->count();
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

            $orden = Orden::with('detalles.producto')->findOrFail($id);

            // Verificar que no esté ya anulada
            if ($orden->estado === 'anulada') {
                return redirect()->back()->with('error', 'Este recibo ya está anulado.');
            }

            // Verificar que sea una venta (no un reembolso)
            if ($orden->tipo !== 'venta') {
                return redirect()->back()->with('error', 'Solo se pueden anular recibos de venta.');
            }

            // Marcar la orden como anulada
            $orden->estado = 'anulada';
            $orden->save();

            // Revertir stock de insumos para cada producto en la orden
            foreach ($orden->detalles as $detalle) {
                $producto = $detalle->producto;
                $cantidadProducto = $detalle->cantidad;

                // Obtener insumos del producto (receta)
                $insumosProducto = $producto->insumos;

                foreach ($insumosProducto as $insumoPivot) {
                    $insumo = $insumoPivot;
                    $cantidadRequerida = $insumoPivot->pivot->cantidad_requerida;
                    $cantidadTotal = $cantidadRequerida * $cantidadProducto;

                    // Stock anterior
                    $stockAnterior = $insumo->stock_actual;

                    // Revertir stock (sumar)
                    $insumo->stock_actual += $cantidadTotal;
                    $insumo->save();

                    // Registrar en bitácora de inventario
                    BitacoraInventario::create([
                        'insumo_id' => $insumo->id,
                        'orden_id' => $orden->id,
                        'tipo_movimiento' => 'reembolso',
                        'cantidad_anterior' => $stockAnterior,
                        'cantidad_movimiento' => $cantidadTotal,
                        'cantidad_nueva' => $insumo->stock_actual,
                        'motivo' => 'Anulación de recibo #'.$orden->numero_recibo.' - Producto: '.$producto->nombre,
                        'user_id' => Auth::id(),
                    ]);
                }
            }

            DB::commit();

            return redirect()->back()->with('success', 'Recibo anulado correctamente. Stock revertido y bitácora actualizada.');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Error al anular recibo: '.$e->getMessage());
        }
    }

    public function createReembolso(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $ordenOriginal = Orden::with('detalles.producto')->findOrFail($id);

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

            // Marcar orden original como anulada
            $ordenOriginal->estado = 'anulada';
            $ordenOriginal->save();

            // Revertir stock de insumos
            foreach ($ordenOriginal->detalles as $detalle) {
                $producto = $detalle->producto;
                $cantidadProducto = $detalle->cantidad;

                $insumosProducto = $producto->insumos;

                foreach ($insumosProducto as $insumoPivot) {
                    $insumo = $insumoPivot;
                    $cantidadRequerida = $insumoPivot->pivot->cantidad_requerida;
                    $cantidadTotal = $cantidadRequerida * $cantidadProducto;

                    $stockAnterior = $insumo->stock_actual;
                    $insumo->stock_actual += $cantidadTotal;
                    $insumo->save();

                    BitacoraInventario::create([
                        'insumo_id' => $insumo->id,
                        'orden_id' => $reembolso->id,
                        'tipo_movimiento' => 'reembolso',
                        'cantidad_anterior' => $stockAnterior,
                        'cantidad_movimiento' => $cantidadTotal,
                        'cantidad_nueva' => $insumo->stock_actual,
                        'motivo' => 'Reembolso de recibo #'.$ordenOriginal->numero_recibo.' - Producto: '.$producto->nombre,
                        'user_id' => Auth::id(),
                    ]);
                }
            }

            DB::commit();

            return redirect()->back()->with('success', 'Reembolso creado correctamente. Stock revertido.');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Error al crear reembolso: '.$e->getMessage());
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
