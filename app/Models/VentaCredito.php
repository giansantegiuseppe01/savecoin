<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class VentaCredito extends Model
{
    protected $table = 'ventas_credito';

    protected $fillable = ['orden_id', 'cliente_id', 'monto', 'saldo', 'estado'];

    protected $casts = [
        'monto' => 'decimal:2',
        'saldo' => 'decimal:2',
    ];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function abonos(): HasMany
    {
        return $this->hasMany(AbonoCredito::class, 'venta_credito_id');
    }

    /**
     * Deudas a crédito que siguen vigentes: saldo pendiente y ticket no anulado.
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('ventas_credito.estado', 'pendiente')
            ->whereExists(fn ($s) => $s->selectRaw('1')
                ->from('ordens')
                ->whereColumn('ordens.id', 'ventas_credito.orden_id')
                ->whereIn('ordens.estado', ['cerrada', 'completada']));
    }

    /**
     * Registra la venta a crédito de un ticket cerrado. Se llama después de descontar inventario.
     */
    public static function registrarDesde(Orden $orden): ?self
    {
        if ($orden->tipo !== 'venta' || $orden->estado !== 'cerrada') {
            return null;
        }

        if (! $orden->cliente_id) {
            throw ValidationException::withMessages([
                'metodo_pago' => 'Para vender a crédito debes seleccionar un cliente.',
            ]);
        }

        return self::firstOrCreate(
            ['orden_id' => $orden->getKey()],
            [
                'cliente_id' => $orden->cliente_id,
                'monto' => $orden->total,
                'saldo' => $orden->total,
                'estado' => 'pendiente',
            ],
        );
    }
}
