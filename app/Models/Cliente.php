<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nombre',
        'apellido',
        'cedula',
        'telefono',
        'correo',
        'synced_at',
        'is_local',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'is_local' => 'boolean',
    ];

    public function ordens(): HasMany
    {
        return $this->hasMany(Orden::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} {$this->apellido}";
    }

    /** Tickets sin cobrar (órdenes de venta abiertas) de este cliente. */
    public function ticketsAbiertos(): HasMany
    {
        return $this->ordens()->where('tipo', 'venta')->where('estado', 'abierta');
    }

    public function getTotalOrdenesAttribute(): int
    {
        return $this->attributes['ordenes_total'] ?? $this->ordens()->count();
    }

    /** Lo que ya pagó: ventas cobradas (no cuenta tickets abiertos, anulados ni reembolsos). */
    public function getTotalGastadoAttribute(): float
    {
        return (float) ($this->attributes['pagado_sum']
            ?? $this->ordens()->where('tipo', 'venta')->whereIn('estado', ['cerrada', 'completada'])->sum('total'));
    }

    /** Lo que debe: suma de sus tickets abiertos. */
    public function getDeudaAttribute(): float
    {
        return (float) ($this->attributes['deuda_sum'] ?? $this->ticketsAbiertos()->sum('total'));
    }

    public function getTicketsAbiertosCountAttribute(): int
    {
        return (int) ($this->attributes['tickets_abiertos'] ?? $this->ticketsAbiertos()->count());
    }
}
