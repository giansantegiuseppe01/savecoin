<?php

namespace App\Services;

use App\Models\LibroContable;
use Illuminate\Support\Facades\DB;

/**
 * Movimientos manuales del libro contable (en bolívares) con saldo corrido.
 */
class LibroContableService
{
    public function saldoActual(): float
    {
        return (float) (LibroContable::orderByDesc('fecha')->orderByDesc('id')->value('saldo_despues') ?? 0);
    }

    /** Saldo acumulado hasta el final de una fecha (incluida). */
    public function saldoHasta(string $fecha): float
    {
        $entradas = (float) LibroContable::whereDate('fecha', '<=', $fecha)->where('tipo_movimiento', 'entrada')->sum('monto');
        $salidas = (float) LibroContable::whereDate('fecha', '<=', $fecha)->where('tipo_movimiento', 'salida')->sum('monto');

        return round($entradas - $salidas, 4);
    }

    /**
     * Registra una entrada o salida y recalcula los saldos en orden cronológico.
     *
     * @param  array{fecha:string,tipo_movimiento:string,monto:float|int|string,descripcion:string,referencia?:?string,categoria_id?:int|string|null}  $datos
     *
     * @throws \DomainException si una salida supera el saldo disponible a esa fecha
     */
    public function registrar(array $datos, ?int $userId): LibroContable
    {
        return DB::transaction(function () use ($datos, $userId) {
            $monto = round((float) $datos['monto'], 4);
            $saldoPrevio = $this->saldoHasta($datos['fecha']);

            if ($datos['tipo_movimiento'] === 'salida' && $monto > $saldoPrevio + 0.00005) {
                throw new \DomainException('Saldo insuficiente: a esa fecha hay Bs '.number_format($saldoPrevio, 2).' y la salida es de Bs '.number_format($monto, 2).'.');
            }

            $movimiento = LibroContable::create([
                'fecha' => $datos['fecha'],
                'tipo_movimiento' => $datos['tipo_movimiento'],
                'monto' => $monto,
                'descripcion' => $datos['descripcion'],
                'saldo_despues' => $datos['tipo_movimiento'] === 'entrada' ? $saldoPrevio + $monto : $saldoPrevio - $monto,
                'referencia' => $datos['referencia'] ?? null,
                'categoria_id' => $datos['categoria_id'] ?? null,
                'user_id' => $userId,
            ]);

            $this->recalcularSaldos();

            return $movimiento->fresh(['categoria', 'user']);
        });
    }

    /** Recalcula el saldo de todos los movimientos en orden cronológico (fecha, id). */
    public function recalcularSaldos(): void
    {
        $saldo = 0.0;

        foreach (LibroContable::orderBy('fecha')->orderBy('id')->get() as $movimiento) {
            $saldo += $movimiento->tipo_movimiento === 'entrada' ? (float) $movimiento->monto : -(float) $movimiento->monto;
            $saldo = round($saldo, 4);

            if (abs((float) $movimiento->saldo_despues - $saldo) > 0.00005) {
                LibroContable::whereKey($movimiento->id)->update(['saldo_despues' => $saldo]);
            }
        }
    }
}
