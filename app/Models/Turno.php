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
     * Generar descripción para libro contable
     */
    public function getDescripcionContable(): string
    {
        $nombreUsuario = strtoupper($this->usuario_nombre);

        return "TURNO {$nombreUsuario}";
    }
}
