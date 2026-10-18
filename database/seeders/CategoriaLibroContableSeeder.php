<?php

namespace Database\Seeders;

use App\Models\CategoriaLibroContable;
use Illuminate\Database\Seeder;

class CategoriaLibroContableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categorias = [
            [
                'nombre' => 'Ventas',
                'descripcion' => 'Ventas generadas por el sistema al cerrar turnos',
                'color' => '#28a745',
                'icono' => 'bi-cash',
                'activo' => true,
            ],
            [
                'nombre' => 'Compra Insumos',
                'descripcion' => 'Compras de ingredientes y suministros',
                'color' => '#dc3545',
                'icono' => 'bi-cart',
                'activo' => true,
            ],
            [
                'nombre' => 'Nómina',
                'descripcion' => 'Pagos de salarios y nómina',
                'color' => '#6f42c1',
                'icono' => 'bi-people',
                'activo' => true,
            ],
            [
                'nombre' => 'Servicios',
                'descripcion' => 'Pagos de servicios (luz, agua, internet)',
                'color' => '#fd7e14',
                'icono' => 'bi-lightning',
                'activo' => true,
            ],
            [
                'nombre' => 'Inversión',
                'descripcion' => 'Inversiones en equipos o mejoras',
                'color' => '#20c997',
                'icono' => 'bi-tools',
                'activo' => true,
            ],
            [
                'nombre' => 'Otros',
                'descripcion' => 'Otros movimientos no categorizados',
                'color' => '#6c757d',
                'icono' => 'bi-three-dots',
                'activo' => true,
            ],
        ];

        foreach ($categorias as $categoria) {
            CategoriaLibroContable::firstOrCreate(['nombre' => $categoria['nombre']], $categoria);
        }
    }
}
