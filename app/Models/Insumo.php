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
        'rendimiento',
        'activo',
    ];

    protected $casts = [
        'costo_unitario' => 'decimal:4',
        'stock_actual' => 'decimal:4',
        'stock_minimo' => 'decimal:4',
        'rendimiento' => 'decimal:4',
        'activo' => 'boolean',
    ];

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_insumos')
            ->withPivot('cantidad_requerida', 'unidad_medida')
            ->withTimestamps();
    }

    /** Ingredientes de un insumo preparado (cantidad por lote en la unidad de cada ingrediente). */
    public function ingredientes(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'insumo_ingredientes', 'insumo_id', 'ingrediente_id')
            ->withPivot('cantidad')
            ->withTimestamps();
    }

    public function esPreparado(): bool
    {
        return $this->rendimiento !== null && (float) $this->rendimiento > 0 && $this->ingredientes()->exists();
    }

    /**
     * Guarda la receta del preparado (reemplaza la anterior) y recalcula su costo unitario.
     *
     * @param  array<int,array{insumo_id:int|string,cantidad:float|string}>  $filas
     */
    public function guardarReceta(?float $rendimiento, array $filas): void
    {
        $sync = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila['insumo_id'] ?? 0);
            $cantidad = (float) ($fila['cantidad'] ?? 0);
            if ($id > 0 && $id !== $this->id && $cantidad > 0) {
                $sync[$id] = ['cantidad' => ($sync[$id]['cantidad'] ?? 0) + $cantidad];
            }
        }

        $this->ingredientes()->sync($sync);
        $this->rendimiento = $sync === [] ? null : $rendimiento;
        $this->save();
        $this->recalcularCostoPreparado();
    }

    /** Costo por unidad = costo de los ingredientes de un lote / lo que rinde el lote. */
    public function recalcularCostoPreparado(): void
    {
        if (! $this->esPreparado()) {
            return;
        }

        $costoLote = $this->ingredientes()->get()->sum(
            fn (self $i) => (float) $i->costo_unitario * (float) $i->pivot->cantidad
        );
        $this->costo_unitario = round($costoLote / (float) $this->rendimiento, 4);
        $this->save();
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(BitacoraInventario::class);
    }
}
