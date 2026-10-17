<?php

namespace App\Services;

use App\Models\Insumo;
use Illuminate\Database\Eloquent\Collection;

class StockAlertService
{
    /**
     * Obtener insumos con stock bajo (stock_actual < stock_minimo)
     */
    public function getLowStockItems(): Collection
    {
        return Insumo::whereColumn('stock_actual', '<', 'stock_minimo')
            ->where('activo', true)
            ->orderBy('stock_actual')
            ->get();
    }

    /**
     * Obtener insumos con stock crítico (menos del 50% del mínimo)
     */
    public function getCriticalStockItems(): Collection
    {
        return Insumo::whereRaw('stock_actual < (stock_minimo * 0.5)')
            ->where('activo', true)
            ->orderBy('stock_actual')
            ->get();
    }

    /**
     * Obtener contadores de alertas para el dashboard
     */
    public function getAlertCounters(): array
    {
        $totalInsumos = Insumo::where('activo', true)->count();
        $stockBajo = Insumo::whereColumn('stock_actual', '<', 'stock_minimo')
            ->where('activo', true)
            ->count();
        $stockCritico = Insumo::whereRaw('stock_actual < (stock_minimo * 0.5)')
            ->where('activo', true)
            ->count();

        return [
            'total_insumos' => $totalInsumos,
            'stock_bajo' => $stockBajo,
            'stock_critico' => $stockCritico,
            'porcentaje_bajo' => $totalInsumos > 0 ? round(($stockBajo / $totalInsumos) * 100, 1) : 0,
        ];
    }
}
