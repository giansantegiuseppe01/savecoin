<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Orden;
use App\Services\ReciboPdfService;
use App\Services\StockAlertService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio')
            ? Carbon::parse($request->input('fecha_inicio'))->startOfDay()
            : now()->startOfDay();
        $fechaFin = $request->input('fecha_fin')
            ? Carbon::parse($request->input('fecha_fin'))->endOfDay()
            : now()->endOfDay();
        $turno = $request->input('turno');
        $colaboradorId = $request->input('colaborador_id');

        $query = Orden::whereBetween('fecha_hora', [$fechaInicio, $fechaFin])
            ->where('estado', '!=', 'anulada');

        if ($turno) {
            $query->where('turno', $turno);
        }

        if ($colaboradorId) {
            $query->where('user_id', $colaboradorId);
        }

        $ordens = $query->get();

        // Obtener tasa de cambio
        $tasaCambio = Configuracion::getTasaCambio();

        // Alertas de stock
        $stockAlertService = new StockAlertService;
        $stockAlerts = $stockAlertService->getAlertCounters();
        $stockBajoItems = $stockAlertService->getLowStockItems();

        // KPIs (convertidos a BS)
        $ventasBrutas = $ordens->where('tipo', 'venta')->sum('total') * $tasaCambio;
        $reembolsos = $ordens->where('tipo', 'reembolso')->sum('total') * $tasaCambio;
        $descuentos = $ordens->sum('descuento') * $tasaCambio;
        $ventasNetas = $ventasBrutas - $descuentos;
        $costoInsumos = $ordens->sum('costo_insumos') * $tasaCambio;
        $beneficioBruto = $ventasNetas - $costoInsumos;

        // Datos para gráfica (ventas por día)
        $ventasPorDia = Orden::whereBetween('fecha_hora', [$fechaInicio, $fechaFin])
            ->where('estado', '!=', 'anulada')
            ->where('tipo', 'venta')
            ->select(
                DB::raw('DATE(fecha_hora) as fecha'),
                DB::raw('SUM(total) as bruto'),
                DB::raw('SUM(descuento) as descuento'),
                DB::raw('SUM(total - descuento) as neto'),
                DB::raw('SUM(costo_insumos) as costo_insumos'),
                DB::raw('SUM(total - descuento - costo_insumos) as beneficio')
            )
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        // Datos para tabla desglose diario
        $desgloseDiario = $ventasPorDia;

        // Guardar filtros para PDF
        $filtros = [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'turno' => $turno,
            'colaborador_id' => $colaboradorId,
        ];

        return view('dashboard.index', compact(
            'ventasBrutas',
            'reembolsos',
            'descuentos',
            'ventasNetas',
            'beneficioBruto',
            'ventasPorDia',
            'desgloseDiario',
            'fechaInicio',
            'fechaFin',
            'turno',
            'colaboradorId',
            'tasaCambio',
            'stockAlerts',
            'stockBajoItems',
            'filtros'
        ));
    }

    public function pdf(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio')
            ? Carbon::parse($request->input('fecha_inicio'))->startOfDay()
            : now()->startOfDay();
        $fechaFin = $request->input('fecha_fin')
            ? Carbon::parse($request->input('fecha_fin'))->endOfDay()
            : now()->endOfDay();
        $turno = $request->input('turno');
        $colaboradorId = $request->input('colaborador_id');

        $query = Orden::whereBetween('fecha_hora', [$fechaInicio, $fechaFin])
            ->where('estado', '!=', 'anulada');

        if ($turno) {
            $query->where('turno', $turno);
        }

        if ($colaboradorId) {
            $query->where('user_id', $colaboradorId);
        }

        $ordens = $query->with(['user', 'cliente', 'detalles.producto'])->get();

        if ($ordens->isEmpty()) {
            return back()->with('error', 'No hay ventas en el período seleccionado para generar el PDF.');
        }

        $filtros = [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'turno' => $turno,
            'colaborador_id' => $colaboradorId,
        ];

        $tasaCambio = Configuracion::getTasaCambio();

        // Calcular totales
        $ventasBrutas = $ordens->where('tipo', 'venta')->sum('total');
        $reembolsos = $ordens->where('tipo', 'reembolso')->sum('total');
        $descuentos = $ordens->sum('descuento');
        $ventasNetas = $ventasBrutas - $descuentos;
        $costoInsumos = $ordens->sum('costo_insumos');
        $beneficioBruto = $ventasNetas - $costoInsumos;

        $pdfService = new ReciboPdfService;

        return $pdfService->generarDashboard($ordens, $filtros, $tasaCambio, [
            'ventasBrutas' => $ventasBrutas,
            'reembolsos' => $reembolsos,
            'descuentos' => $descuentos,
            'ventasNetas' => $ventasNetas,
            'costoInsumos' => $costoInsumos,
            'beneficioBruto' => $beneficioBruto,
        ]);
    }

    public function getColaboradores()
    {
        $colaboradores = DB::table('users')
            ->whereIn('rol', ['empleado', 'admin'])
            ->select('id', 'nombre', 'apellido')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => trim($u->nombre.' '.$u->apellido),
            ]);

        return response()->json($colaboradores);
    }
}
