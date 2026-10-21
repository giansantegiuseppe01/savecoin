<?php

namespace App\Console\Commands;

use App\Models\LibroContable;
use App\Models\Turno;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige los asientos de cierre de turno hechos antes de convertir a bolívares:
 * asentaban el total en USD en un libro que está en Bs.
 */
class CorregirLibroTurnos extends Command
{
    protected $signature = 'libro:corregir-turnos {--aplicar : Guarda los cambios (sin esta opción solo muestra qué cambiaría)}';

    protected $description = 'Recalcula en Bs los asientos del libro contable generados por cierres de turno';

    public function handle(): int
    {
        $cambios = [];

        foreach (LibroContable::where('referencia', 'like', 'turno\_%')->get() as $asiento) {
            $turno = Turno::find((int) substr($asiento->referencia, 6));
            if (! $turno) {
                $this->warn("{$asiento->referencia}: el turno ya no existe, se omite.");

                continue;
            }

            $correcto = $turno->totalVentasBs();
            if ($correcto <= 0 || abs($correcto - (float) $asiento->monto) < 0.01) {
                continue;
            }

            $cambios[] = [$asiento, $turno, $correcto];
            $this->line(sprintf(
                '%s: Bs %s -> Bs %s (USD %s)',
                $asiento->referencia,
                number_format((float) $asiento->monto, 2),
                number_format($correcto, 2),
                number_format((float) $turno->total_ventas, 2),
            ));
        }

        if (! $cambios) {
            $this->info('No hay asientos que corregir.');

            return self::SUCCESS;
        }

        if (! $this->option('aplicar')) {
            $this->comment('Simulación: no se guardó nada. Ejecute de nuevo con --aplicar para corregirlos.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($cambios) {
            foreach ($cambios as [$asiento, $turno, $correcto]) {
                $asiento->monto = $correcto;
                if (! str_contains($asiento->descripcion, '(USD')) {
                    $asiento->descripcion .= ' (USD '.number_format((float) $turno->total_ventas, 2).')';
                }
                $asiento->save();
            }

            $saldo = 0.0;
            foreach (LibroContable::orderBy('fecha')->orderBy('created_at')->orderBy('id')->get() as $mov) {
                $saldo += $mov->tipo_movimiento === 'entrada' ? (float) $mov->monto : -(float) $mov->monto;
                $mov->saldo_despues = $saldo;
                $mov->save();
            }
        });

        $this->info(count($cambios).' asiento(s) corregido(s) y saldos recalculados.');

        return self::SUCCESS;
    }
}
