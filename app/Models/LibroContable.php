<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LibroContable extends Model
{
    use SoftDeletes;

    protected $table = 'libro_contable';

    protected $fillable = [
        'fecha',
        'tipo_movimiento',
        'monto',
        'descripcion',
        'saldo_despues',
        'referencia',
        'categoria_id',
        'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:4',
        'saldo_despues' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaLibroContable::class, 'categoria_id');
    }
}
