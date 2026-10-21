<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Orden extends Model
{
    use HasFactory, SoftDeletes;

    // El id es texto (numérico desde la secuencia o UUID desde la app móvil): sin esto
    // Eloquent lo convierte a entero y un UUID se corrompe (p. ej. "5097e84d-…" -> 5097).
    protected $keyType = 'string';

    protected $fillable = [
        'numero_recibo',
        'user_id',
        'turno_id',
        'cliente_id',
        'cliente_nombre',
        'tipo',
        'subtotal',
        'descuento',
        'impuesto',
        'total',
        'tasa_cambio',
        'aplica_iva',
        'costo_insumos',
        'beneficio_bruto',
        'estado',
        'metodo_pago',
        'referencia_pago',
        'turno',
        'fecha_hora',
        'notas',
        'recibo_anulado_id',
        'synced_at',
        'is_local',
    ];

    protected $casts = [
        'subtotal' => 'decimal:4',
        'descuento' => 'decimal:4',
        'impuesto' => 'decimal:4',
        'total' => 'decimal:4',
        'tasa_cambio' => 'decimal:4',
        'aplica_iva' => 'boolean',
        'costo_insumos' => 'decimal:4',
        'beneficio_bruto' => 'decimal:4',
        'fecha_hora' => 'datetime',
        'synced_at' => 'datetime',
        'is_local' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleOrden::class);
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(BitacoraInventario::class);
    }

    /**
     * Verificar si la orden está cerrada
     */
    public function isCerrada(): bool
    {
        return $this->estado === 'cerrada';
    }

    /**
     * Verificar si la orden está abierta
     */
    public function isAbierta(): bool
    {
        return $this->estado === 'abierta';
    }

    /**
     * Verificar si la orden está anulada
     */
    public function isAnulada(): bool
    {
        return $this->estado === 'anulada';
    }

    /**
     * Cerrar la orden (convertir en recibo)
     */
    public function cerrar(): void
    {
        $this->estado = 'cerrada';
        $this->save();
    }

    /**
     * Anular la orden
     */
    public function anular(): void
    {
        $this->estado = 'anulada';
        $this->save();
    }
}
