<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turno extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'user_id',
        'usuario_nombre',
        'fecha',
        'hora_apertura',
        'hora_cierre',
        'estado', // 'abierto', 'cerrado'
        'total_ventas',
        'numero_ordenes',
        'monto_apertura',
        'notas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_apertura' => 'datetime',
        'hora_cierre' => 'datetime',
        'total_ventas' => 'decimal:4',
        'monto_apertura' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ordens(): HasMany
    {
        return $this->hasMany(Orden::class);
    }

    /**
     * Verificar si el turno está abierto
     */
    public function isAbierto(): bool
    {
        return $this->estado === 'abierto';
    }

    /**
     * Verificar si el turno está cerrado
     */
    public function isCerrado(): bool
    {
        return $this->estado === 'cerrado';
    }

    /**
     * Generar código del turno
     */
    public static function generarCodigo(User $user): string
    {
        $fecha = now()->format('d/m/Y');
        $nombreUsuario = strtoupper($user->nombre.' '.$user->apellido);
        $numeroSecuencia = now()->format('His'); // Hora minuto segundo para unicidad

        return "TURNO {$nombreUsuario} - {$fecha} - {$numeroSecuencia}";
    }

    /**
     * Cerrar turno y calcular totales
     */
    public function cerrar(): void
    {
        $this->estado = 'cerrado';
        $this->hora_cierre = now();
        $this->total_ventas = $this->ordens()->where('estado', 'cerrada')->sum('total');
        $this->numero_ordenes = $this->ordens()->where('estado', 'cerrada')->count();
        $this->save();
    }

    /**
     * Total de las ventas cerradas del turno en bolívares. Cada orden se convierte con
     * su propia tasa de cambio (la vigente cuando se vendió); `total_ventas` queda en USD.
     */
    public function totalVentasBs(): float
    {
        $tasaActual = (float) Configuracion::getTasaCambio();

        return round($this->ordens()->where('estado', 'cerrada')->get()->sum(function ($orden) use ($tasaActual) {
            $tasa = (float) $orden->tasa_cambio > 0 ? (float) $orden->tasa_cambio : $tasaActual;

            return (float) $orden->total * $tasa;
        }), 2);
    }

    /**
     * Resumen del turno para el reporte impreso al cerrar: totales y recaudación por método de pago
     * (USD y Bs; cada orden se convierte con su propia tasa).
     */
    public function resumenCierre(): array
    {
        $tasaActual = (float) Configuracion::getTasaCambio();
        $metodos = [];
        $totalUsd = 0.0;
        $totalBs = 0.0;
        $cantidad = 0;

        foreach ($this->ordens()->where('estado', 'cerrada')->get() as $orden) {
            $tasa = (float) $orden->tasa_cambio > 0 ? (float) $orden->tasa_cambio : $tasaActual;
            $usd = (float) $orden->total;
            $metodo = $orden->metodo_pago ?: 'sin_metodo';

            $metodos[$metodo] ??= ['metodo' => $metodo, 'ordenes' => 0, 'total_usd' => 0.0, 'total_bs' => 0.0];
            $metodos[$metodo]['ordenes']++;
            $metodos[$metodo]['total_usd'] += $usd;
            $metodos[$metodo]['total_bs'] += $usd * $tasa;

            $totalUsd += $usd;
            $totalBs += $usd * $tasa;
            $cantidad++;
        }

        return [
            'turno_id' => $this->id,
            'codigo' => $this->codigo,
            'usuario' => $this->usuario_nombre,
            'hora_apertura' => $this->hora_apertura?->toIso8601String(),
            'hora_cierre' => $this->hora_cierre?->toIso8601String(),
            'numero_ordenes' => $cantidad,
            'total_usd' => round($totalUsd, 2),
            'total_bs' => round($totalBs, 2),
            'metodos' => array_values(array_map(fn ($m) => [
                'metodo' => $m['metodo'],
                'ordenes' => $m['ordenes'],
                'total_usd' => round($m['total_usd'], 2),
                'total_bs' => round($m['total_bs'], 2),
            ], $metodos)),
        ];
    }

    /**
     * Generar descripción para libro contable
     */
    public function getDescripcionContable(): string
    {
        $nombreUsuario = strtoupper($this->usuario_nombre);

        return "TURNO {$nombreUsuario}";
    }
}
