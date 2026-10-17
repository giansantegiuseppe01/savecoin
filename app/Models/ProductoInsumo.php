<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoInsumo extends Model
{
    use HasFactory;

    protected $fillable = [
        'producto_id',
        'insumo_id',
        'cantidad_requerida',
        'unidad_medida',
    ];

    protected $casts = [
        'cantidad_requerida' => 'decimal:4',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }
}
