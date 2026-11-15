<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbonoCredito extends Model
{
    protected $table = 'abonos_credito';

    protected $fillable = [
        'venta_credito_id', 'turno_id', 'user_id', 'monto', 'tasa_cambio', 'forma_pago', 'referencia',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'tasa_cambio' => 'decimal:4',
    ];

    public function ventaCredito(): BelongsTo
    {
        return $this->belongsTo(VentaCredito::class, 'venta_credito_id');
    }
}
