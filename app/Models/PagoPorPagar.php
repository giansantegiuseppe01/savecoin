<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoPorPagar extends Model
{
    protected $table = 'pagos_por_pagar';

    protected $fillable = ['cuenta_por_pagar_id', 'monto', 'forma_pago', 'referencia', 'fecha', 'user_id'];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'date',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaPorPagar::class, 'cuenta_por_pagar_id');
    }
}
