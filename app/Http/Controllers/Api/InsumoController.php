<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Insumo;
use App\Services\StockAlertService;

class InsumoController extends Controller
{
    public function show($id)
    {
        $insumo = Insumo::findOrFail($id);

        return response()->json($insumo);
    }

    public function stockAlerts()
    {
        $stockAlertService = new StockAlertService;

        return response()->json([
            'counters' => $stockAlertService->getAlertCounters(),
            'low_stock_items' => $stockAlertService->getLowStockItems(),
            'critical_items' => $stockAlertService->getCriticalStockItems(),
        ]);
    }
}
