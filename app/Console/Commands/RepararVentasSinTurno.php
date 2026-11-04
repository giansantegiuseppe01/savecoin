<?php

namespace App\Console\Commands;

use App\Models\Orden;
use App\Models\Turno;
use Illuminate\Console\Command;

/**
 * Asigna a su turno las ventas cerradas que se sincronizaron sin turno y asienta en el libro
 * contable lo que el cierre no incluyó.
 */
class RepararVentasSinTurno extends Command
{
    protected $signature = 'savecoin:reparar-ventas-sin-turno {--aplicar : Aplica los cambios (sin esta opción solo muestra qué haría)}';

    protected $description = 'Asigna a su turno las ventas cerradas sin turno y corrige turno y libro contable';

    public function handle(): int
    {
        $huerfanas = Orden::whereNull('turno_id')->where('estado', 'cerrada')->where('tipo', 'venta')->orderBy('fecha_hora')->get();
        $this->info("Ventas cerradas sin turno: {$huerfanas->count()}");

        foreach ($huerfanas as $orden) {
            $turno = Turno::paraVentaSincronizada((int) $orden->user_id, $orden->fecha_hora);
            $linea = "Recibo {$orden->numero_recibo} · USD {$orden->total} · ".($turno ? "turno #{$turno->id} ({$turno->estado})" : 'SIN turno posible');
            $this->line($linea);

            if ($turno && $this->option('aplicar')) {
                $turno->incorporarVentaTardia($orden, null);
            }
        }

        $this->option('aplicar') ? $this->info('Cambios aplicados.') : $this->comment('Simulación: ejecuta con --aplicar para guardar.');

        return self::SUCCESS;
    }
}
