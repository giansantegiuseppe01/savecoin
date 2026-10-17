<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Insumo extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'costo_unitario',
        'stock_actual',
        'unidad_medida',
        'stock_minimo',
        'activo',
    ];

    protected $casts = [
        'costo_unitario' => 'decimal:4',
        'stock_actual' => 'decimal:4',
        'stock_minimo' => 'decimal:4',
        'activo' => 'boolean',
    ];

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_insumos')
            ->withPivot('cantidad_requerida', 'unidad_medida')
            ->withTimestamps();
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(BitacoraInventario::class);
    }
}
