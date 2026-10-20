<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DetalleOrden extends Model
{
    use HasFactory;

    // El id es un UUID generado en el hook `creating`. Sin esto Eloquent lo convierte a
    // entero al leerlo y varios detalles terminan con el mismo id en el JSON (p. ej. todos
    // los UUID que empiezan por letra pasan a 0), y la app los fusiona en uno solo.
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'orden_id',
        'producto_id',
        'producto_nombre',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'descuento',
        'total',
        'notas',
        'fue_modificado',
        'synced_at',
        'is_local',
    ];

    protected $casts = [
        'precio_unitario' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'descuento' => 'decimal:4',
        'total' => 'decimal:4',
        'fue_modificado' => 'boolean',
        'synced_at' => 'datetime',
        'is_local' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($detalle) {
            if (empty($detalle->id)) {
                $detalle->id = (string) Str::uuid();
            }
        });
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Verificar si el detalle fue modificado
     */
    public function fueModificado(): bool
    {
        return $this->fue_modificado;
    }

    /**
     * Marcar como modificado
     */
    public function marcarComoModificado(): void
    {
        $this->fue_modificado = true;
        $this->save();
    }
}
