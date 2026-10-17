<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuracions';

    protected $fillable = [
        'nombre_empresa',
        'logo',
        'rif',
        'tasa_cambio',
        'eslogan',
        'direccion',
        'telefono',
        'email',
    ];

    protected $casts = [
        'tasa_cambio' => 'decimal:4',
    ];

    public static function getTasaCambio(): float
    {
        $config = self::first();

        return $config ? $config->tasa_cambio : 1.0000;
    }

    public static function getConfig()
    {
        return self::first() ?? self::create([
            'nombre_empresa' => 'Mi Restaurante',
            'tasa_cambio' => 1.0000,
        ]);
    }
}
