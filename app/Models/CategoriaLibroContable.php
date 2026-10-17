<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaLibroContable extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'color',
        'icono',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function movimientos()
    {
        return $this->hasMany(LibroContable::class, 'categoria_id');
    }
}
