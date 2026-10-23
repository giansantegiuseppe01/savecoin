<?php

namespace App\Services;

use App\Models\BitacoraInventario;
use App\Models\Insumo;
use App\Models\Producto;

class InventarioService
{
    /**
     * Descuenta del inventario lo que consume la venta de $cantidad unidades de $producto
     * (incluyendo, si es un combo, los insumos de todos sus productos incluidos) y deja
     * registro en la bitácora. Devuelve el costo de los insumos consumidos.
     *
     * @param  array<int,string>  $advertencias  Se agregan avisos de stock insuficiente.
     */
    public function descontarVenta(
        Producto $producto,
        float $cantidad,
        ?string $ordenId,
        string $referencia,
        ?int $userId,
        array &$advertencias,
    ): float {
        $costo = 0.0;

        foreach ($producto->consumoInsumos($cantidad) as $insumoId => $cantidadTotal) {
            $insumo = Insumo::find($insumoId);
            if (! $insumo) {
                continue;
            }

            if ($insumo->stock_actual < $cantidadTotal) {
                $advertencias[] = "Stock insuficiente para {$insumo->nombre}: disponible {$insumo->stock_actual}, requerido {$cantidadTotal}";
            }

            $costo += (float) $insumo->costo_unitario * $cantidadTotal;

            $stockAnterior = $insumo->stock_actual;
            $insumo->stock_actual -= $cantidadTotal;
            $insumo->save();

            BitacoraInventario::create([
                'insumo_id' => $insumo->id,
                'orden_id' => $ordenId,
                'tipo_movimiento' => 'salida',
                'cantidad_anterior' => $stockAnterior,
                'cantidad_movimiento' => $cantidadTotal,
                'cantidad_nueva' => $insumo->stock_actual,
                'motivo' => 'Venta en '.$referencia.' - Producto: '.$producto->nombre,
                'user_id' => $userId,
            ]);
        }

        return $costo;
    }
}
