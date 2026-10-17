<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BitacoraInventario extends Model
{
    use HasFactory;

    protected $fillable = [
        'insumo_id',
        'orden_id',
        'tipo_movimiento',
        'cantidad_anterior',
        'cantidad_movimiento',
        'cantidad_nueva',
        'motivo',
        'user_id',
    ];

    protected $casts = [
        'cantidad_anterior' => 'decimal:4',
        'cantidad_movimiento' => 'decimal:4',
        'cantidad_nueva' => 'decimal:4',
    ];

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
