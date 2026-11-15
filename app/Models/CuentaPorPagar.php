<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaPorPagar extends Model
{
    protected $table = 'cuentas_por_pagar';

    public const TIPOS = ['factura' => 'Factura', 'prestamo' => 'Préstamo'];

    protected $fillable = [
        'acreedor', 'tipo', 'concepto', 'numero_documento', 'monto', 'saldo',
        'estado', 'fecha_emision', 'fecha_vencimiento', 'notas', 'user_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'saldo' => 'decimal:2',
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
    ];

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoPorPagar::class, 'cuenta_por_pagar_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Cuentas con saldo que aún no se ha pagado por completo. */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', 'pendiente');
    }

    /** Cuentas pendientes cuyo vencimiento ya pasó. */
    public function scopeVencidas(Builder $query): Builder
    {
        return $query->pendientes()
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString());
    }

    public function getEstaVencidaAttribute(): bool
    {
        return $this->estado === 'pendiente'
            && $this->fecha_vencimiento !== null
            && $this->fecha_vencimiento->lt(now()->startOfDay());
    }
}
