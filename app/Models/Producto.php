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

    /**
     * Productos que forman este combo (cada uno con su cantidad en el pivot).
     */
    public function componentes(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'producto_componentes', 'producto_id', 'componente_id')
            ->withPivot('cantidad')
            ->withTimestamps();
    }

    /**
     * Combos en los que este producto está incluido.
     */
    public function combos(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'producto_componentes', 'componente_id', 'producto_id');
    }

    public function esCombo(): bool
    {
        return $this->componentes()->exists();
    }

    /**
     * Insumos que consume vender $cantidad unidades: los de su propia receta más, de forma
     * recursiva, los de cada producto incluido (un combo puede incluir otros combos).
     *
     * @return array<int,float> insumo_id => cantidad total
     */
    public function consumoInsumos(float $cantidad = 1, int $nivel = 0): array
    {
        if ($nivel > 10) {
            throw new \RuntimeException("El combo '{$this->nombre}' tiene una referencia circular.");
        }

        $consumo = [];

        foreach ($this->insumos as $insumo) {
            $consumo[$insumo->id] = ($consumo[$insumo->id] ?? 0) + (float) $insumo->pivot->cantidad_requerida * $cantidad;
        }

        foreach ($this->componentes as $componente) {
            $sub = $componente->consumoInsumos((float) $componente->pivot->cantidad * $cantidad, $nivel + 1);
            foreach ($sub as $insumoId => $total) {
                $consumo[$insumoId] = ($consumo[$insumoId] ?? 0) + $total;
            }
        }

        return $consumo;
    }

    /**
     * Costo de producción por unidad según los insumos actuales (incluye los productos del combo).
     */
    public function costoCalculado(): float
    {
        $costo = 0.0;
        foreach ($this->consumoInsumos(1) as $insumoId => $cantidad) {
            $insumo = Insumo::find($insumoId);
            $costo += $insumo ? (float) $insumo->costo_unitario * $cantidad : 0;
        }

        return round($costo, 4);
    }

    /**
     * ¿Este producto contiene (directa o indirectamente) a $productoId? Sirve para
     * impedir combos circulares.
     */
    public function incluyeA(int $productoId, int $nivel = 0): bool
    {
        if ($nivel > 10) {
            return true;
        }

        foreach ($this->componentes as $componente) {
            if ($componente->id === $productoId || $componente->incluyeA($productoId, $nivel + 1)) {
                return true;
            }
        }

        return false;
    }

    public function detallesOrden(): HasMany
    {
        return $this->hasMany(DetalleOrden::class);
    }
}
