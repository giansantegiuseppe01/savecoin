<?php

namespace App\Services;

use App\Models\BitacoraInventario;
use App\Models\Insumo;
use App\Models\Producto;

class InventarioService
{
    /**
     * Descuenta del inventario lo que consume la venta de $cantidad unidades de $producto
     * (incluyendo, si es un combo, los insumos de todos sus productos incluidos).
     * Devuelve el costo de los insumos consumidos. No escribe en la bitácora de inventario.
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

            // La venta descuenta el stock pero no se anota en la bitácora: allí solo van
            // las cargas, descargas y preparaciones hechas a mano.
            $insumo->stock_actual -= $cantidadTotal;
            $insumo->save();
        }

        return $costo;
    }

    /**
     * Devuelve al inventario lo que consumió una venta (al anularla, reembolsarla o eliminarla),
     * con la misma regla que al venderla: los combos reponen los insumos de cada producto.
     * Solo repone ventas que sí descontaron stock (cerradas/completadas).
     *
     * @return bool true si se repuso stock
     */
    public function reponerVenta(\App\Models\Orden $orden): bool
    {
        if ($orden->tipo !== 'venta' || ! in_array($orden->estado, ['cerrada', 'completada'], true)) {
            return false;
        }

        $orden->loadMissing('detalles.producto');
        foreach ($orden->detalles as $detalle) {
            if (! $detalle->producto) {
                continue;
            }
            foreach ($detalle->producto->consumoInsumos((float) $detalle->cantidad) as $insumoId => $cantidad) {
                Insumo::whereKey($insumoId)->lockForUpdate()->first()?->increment('stock_actual', $cantidad);
            }
        }

        return true;
    }

    /**
     * Registra una carga (entrada) o descarga (salida) manual de un insumo y la deja en la bitácora.
     * Una salida no puede dejar el stock en negativo.
     *
     * @throws \DomainException si la salida supera el stock disponible
     */
    public function registrarMovimiento(Insumo $insumo, string $tipo, float $cantidad, string $motivo, ?int $userId, bool $descontarIngredientes = true): Insumo
    {
        $stockAnterior = (float) $insumo->stock_actual;

        if ($tipo === 'salida' && $stockAnterior < $cantidad) {
            throw new \DomainException("Stock insuficiente de {$insumo->nombre}: hay {$stockAnterior} y se intenta descargar {$cantidad}.");
        }

        // Cargar un preparado (p. ej. salsa especial) consume sus ingredientes
        if ($tipo === 'entrada' && $descontarIngredientes && $insumo->esPreparado()) {
            $this->descontarIngredientes($insumo, $cantidad, $userId);
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

    /**
     * Qué consumiría preparar $cantidad de un insumo preparado: [['insumo' => Insumo, 'cantidad' => float], ...]
     */
    public function consumoDePreparado(Insumo $preparado, float $cantidad): array
    {
        $factor = $cantidad / (float) $preparado->rendimiento;
        $consumo = [];
        foreach ($preparado->ingredientes()->get() as $ingrediente) {
            $consumo[] = [
                'insumo' => $ingrediente,
                'cantidad' => round((float) $ingrediente->pivot->cantidad * $factor, 4),
            ];
        }

        return $consumo;
    }

    /**
     * Descuenta los ingredientes de $cantidad del preparado. Valida todo antes de mover nada;
     * debe llamarse dentro de una transacción.
     *
     * @throws \DomainException si falta stock de algún ingrediente
     */
    private function descontarIngredientes(Insumo $preparado, float $cantidad, ?int $userId): void
    {
        $consumo = $this->consumoDePreparado($preparado, $cantidad);
        $faltantes = [];
        $bloqueados = [];

        foreach ($consumo as $linea) {
            $ingrediente = Insumo::whereKey($linea['insumo']->id)->lockForUpdate()->first();
            $bloqueados[] = [$ingrediente, $linea['cantidad']];
            if ((float) $ingrediente->stock_actual + 1e-9 < $linea['cantidad']) {
                $faltantes[] = "{$ingrediente->nombre} (hay ".(float) $ingrediente->stock_actual.", se necesitan {$linea['cantidad']})";
            }
        }

        if ($faltantes !== []) {
            throw new \DomainException('No alcanza el stock para preparar '.$preparado->nombre.': '.implode('; ', $faltantes).'.');
        }

        $motivo = 'Preparación de '.$preparado->nombre.' ('.(float) $cantidad.' '.$preparado->unidad_medida.')';
        foreach ($bloqueados as [$ingrediente, $cant]) {
            $anterior = (float) $ingrediente->stock_actual;
            $ingrediente->stock_actual = max(0, $anterior - $cant);
            $ingrediente->save();

            BitacoraInventario::create([
                'insumo_id' => $ingrediente->id,
                'orden_id' => null,
                'tipo_movimiento' => 'salida',
                'cantidad_anterior' => $anterior,
                'cantidad_movimiento' => $cant,
                'cantidad_nueva' => $ingrediente->stock_actual,
                'motivo' => $motivo,
                'user_id' => $userId,
            ]);
        }

        // Los costos de los ingredientes pueden haber cambiado desde la última preparación
        $preparado->recalcularCostoPreparado();
    }
}
