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

    /**
     * Registra una carga (entrada) o descarga (salida) manual de un insumo y la deja en la bitácora.
     * Una salida no puede dejar el stock en negativo.
     *
     * @throws \DomainException si la salida supera el stock disponible
     */
    public function registrarMovimiento(Insumo $insumo, string $tipo, float $cantidad, string $motivo, ?int $userId): Insumo
    {
        $stockAnterior = (float) $insumo->stock_actual;

        if ($tipo === 'salida' && $stockAnterior < $cantidad) {
            throw new \DomainException("Stock insuficiente de {$insumo->nombre}: hay {$stockAnterior} y se intenta descargar {$cantidad}.");
        }

        $insumo->stock_actual = $tipo === 'entrada' ? $stockAnterior + $cantidad : $stockAnterior - $cantidad;
        $insumo->save();

        BitacoraInventario::create([
            'insumo_id' => $insumo->id,
            'orden_id' => null,
            'tipo_movimiento' => $tipo,
            'cantidad_anterior' => $stockAnterior,
            'cantidad_movimiento' => $cantidad,
            'cantidad_nueva' => $insumo->stock_actual,
            'motivo' => $motivo,
            'user_id' => $userId,
        ]);

        return $insumo;
    }
}
