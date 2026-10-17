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

    public function getTotalOrdenesAttribute(): int
    {
        return $this->ordens()->count();
    }

    public function getTotalGastadoAttribute(): float
    {
        return $this->ordens()->where('estado', '!=', 'anulada')->sum('total');
    }
}
