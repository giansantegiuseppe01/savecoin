<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'precio_venta',
        'costo_produccion',
        'categoria',
        'imagen_url',
        'activo',
        'preparado_en_cocina',
        'tiempo_preparacion_minutos',
    ];

    protected $casts = [
        'precio_venta' => 'decimal:4',
        'costo_produccion' => 'decimal:4',
        'activo' => 'boolean',
        'preparado_en_cocina' => 'boolean',
    ];

    public function insumos(): BelongsToMany
    {
        return $this->belongsToMany(Insumo::class, 'producto_insumos')
            ->withPivot('cantidad_requerida', 'unidad_medida')
            ->withTimestamps();
    }

    public function detallesOrden(): HasMany
    {
        return $this->hasMany(DetalleOrden::class);
    }
}
