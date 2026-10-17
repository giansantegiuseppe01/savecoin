<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Orden;
use Barryvdh\DomPDF\Facade\Pdf;

class ReciboPdfService
{
    /**
     * Generar PDF de recibo individual
     */
    public function generarRecibo(Orden $orden)
    {
        $config = Configuracion::getConfig();
        $tasaCambio = $config ? $config->tasa_cambio : 1.0;

        $pdf = Pdf::loadView('pdf.recibo', compact('orden', 'config', 'tasaCambio'))
            ->setPaper('a4')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);

        return $pdf->download("recibo_{$orden->numero_recibo}.pdf");
    }

    /**
     * Generar PDF de recibos (lista con filtros aplicados)
     */
    public function generarRecibos($ordens, $filtros = [])
    {
        $config = Configuracion::getConfig();
        $tasaCambio = $config ? $config->tasa_cambio : 1.0;

        // Calcular totales de los recibos visibles
        $ventasBrutas = $ordens->where('tipo', 'venta')->sum('total');
        $reembolsos = $ordens->where('tipo', 'reembolso')->sum('total');
        $descuentos = $ordens->sum('descuento');
        $ventasNetas = $ventasBrutas - $descuentos;
        $costoInsumos = $ordens->sum('costo_insumos');
        $beneficioBruto = $ventasNetas - $costoInsumos;

        $pdf = Pdf::loadView('pdf.recibos-lista', compact(
            'ordens',
            'config',
            'tasaCambio',
            'filtros',
            'ventasBrutas',
            'reembolsos',
            'descuentos',
            'ventasNetas',
            'costoInsumos',
            'beneficioBruto'
        ))
            ->setPaper('a4')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);

        return $pdf->download('recibos_'.now()->format('Y-m-d_H-i-s').'.pdf');
    }

    /**
     * Generar PDF de libro contable (con filtros aplicados)
     */
    public function generarLibroContable($movimientos, $filtros = [])
    {
        $config = Configuracion::getConfig();
        $tasaCambio = $config ? $config->tasa_cambio : 1.0;

        // Calcular totales
        $totalEntradas = $movimientos->where('tipo_movimiento', 'entrada')->sum('monto');
        $totalSalidas = $movimientos->where('tipo_movimiento', 'salida')->sum('monto');
        $saldoFinal = $movimientos->last()?->saldo_despues ?? 0;

        $pdf = Pdf::loadView('pdf.libro-contable', compact(
            'movimientos',
            'config',
            'tasaCambio',
            'filtros',
            'totalEntradas',
            'totalSalidas',
            'saldoFinal'
        ))
            ->setPaper('a4')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);

        return $pdf->download('libro_contable_'.now()->format('Y-m-d_H-i-s').'.pdf');
    }

    /**
     * Generar PDF de inventario (con filtros aplicados)
     */
    public function generarInventario($insumos, $filtros = [])
    {
        $config = Configuracion::getConfig();

        // Calcular totales
        $totalItems = $insumos->count();
        $valorTotal = $insumos->sum(function ($insumo) {
            return $insumo->stock_actual * $insumo->costo_unitario;
        });

        $pdf = Pdf::loadView('pdf.inventario', compact(
            'insumos',
            'config',
            'filtros',
            'totalItems',
            'valorTotal'
        ))
            ->setPaper('a4')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);

        return $pdf->download('inventario_'.now()->format('Y-m-d_H-i-s').'.pdf');
    }

    /**
     * Generar PDF de bitácora (con filtros aplicados)
     */
    public function generarBitacora($movimientos, $filtros = [])
    {
        $config = Configuracion::getConfig();

        // Calcular totales
        $totalMovimientos = $movimientos->count();
        $totalEntradas = $movimientos->where('tipo_movimiento', 'entrada')->sum('cantidad_movimiento');
        $totalSalidas = $movimientos->where('tipo_movimiento', 'salida')->sum('cantidad_movimiento');

        $pdf = Pdf::loadView('pdf.bitacora', compact(
            'movimientos',
            'config',
            'filtros',
            'totalMovimientos',
            'totalEntradas',
            'totalSalidas'
        ))
            ->setPaper('a4')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);

        return $pdf->download('bitacora_'.now()->format('Y-m-d_H-i-s').'.pdf');
    }

    /**
     * Generar PDF de dashboard (con filtros aplicados)
     */
    public function generarDashboard($ordens, $filtros, $tasaCambio, $totales)
    {
        $config = Configuracion::getConfig();

        $pdf = Pdf::loadView('pdf.dashboard', compact(
            'ordens',
            'config',
            'tasaCambio',
            'filtros',
            'totales'
        ))
            ->setPaper('a4')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);

        return $pdf->download('dashboard_'.now()->format('Y-m-d_H-i-s').'.pdf');
    }
}
