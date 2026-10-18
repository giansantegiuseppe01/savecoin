<?php

namespace Database\Seeders;

use App\Models\Insumo;
use Illuminate\Database\Seeder;

class InsumoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $insumos = [
            [
                'nombre' => 'Harina de Trigo',
                'codigo' => 'INS001',
                'descripcion' => 'Harina de trigo premium para panadería',
                'costo_unitario' => 2.5000,
                'stock_actual' => 50.0000,
                'unidad_medida' => 'kg',
                'stock_minimo' => 10.0000,
                'activo' => true,
            ],
            [
                'nombre' => 'Azúcar Blanca',
                'codigo' => 'INS002',
                'descripcion' => 'Azúcar refinada blanca',
                'costo_unitario' => 1.8000,
                'stock_actual' => 100.0000,
                'unidad_medida' => 'kg',
                'stock_minimo' => 20.0000,
                'activo' => true,
            ],
            [
                'nombre' => 'Huevos',
                'codigo' => 'INS003',
                'descripcion' => 'Huevos frescos de gallina',
                'costo_unitario' => 0.2500,
                'stock_actual' => 200.0000,
                'unidad_medida' => 'unidad',
                'stock_minimo' => 50.0000,
                'activo' => true,
            ],
            [
                'nombre' => 'Leche Entera',
                'codigo' => 'INS004',
                'descripcion' => 'Leche entera pasteurizada',
                'costo_unitario' => 1.2000,
                'stock_actual' => 30.0000,
                'unidad_medida' => 'litro',
                'stock_minimo' => 10.0000,
                'activo' => true,
            ],
            [
                'nombre' => 'Mantequilla',
                'codigo' => 'INS005',
                'descripcion' => 'Mantequilla sin sal',
                'costo_unitario' => 4.5000,
                'stock_actual' => 15.0000,
                'unidad_medida' => 'kg',
                'stock_minimo' => 5.0000,
                'activo' => true,
            ],
            [
                'nombre' => 'Tomate',
                'codigo' => 'INS006',
                'descripcion' => 'Tomates frescos rojos',
                'costo_unitario' => 2.0000,
                'stock_actual' => 25.0000,
                'unidad_medida' => 'kg',
                'stock_minimo' => 8.0000,
                'activo' => true,
            ],
            [
                'nombre' => 'Cebolla',
                'codigo' => 'INS007',
                'descripcion' => 'Cebolla blanca',
                'costo_unitario' => 1.5000,
                'stock_actual' => 30.0000,
                'unidad_medida' => 'kg',
                'stock_minimo' => 10.0000,
                'activo' => true,
            ],
            [
                'nombre' => 'Carne de Res',
                'codigo' => 'INS008',
                'descripcion' => 'Carne de res premium',
                'costo_unitario' => 12.0000,
                'stock_actual' => 20.0000,
                'unidad_medida' => 'kg',
                'stock_minimo' => 5.0000,
                'activo' => true,
            ],
            [
                'nombre' => 'Pollo',
                'codigo' => 'INS009',
                'descripcion' => 'Pechuga de pollo',
                'costo_unitario' => 8.5000,
                'stock_actual' => 25.0000,
                'unidad_medida' => 'kg',
                'stock_minimo' => 8.0000,
                'activo' => true,
            ],
            [
                'nombre' => 'Aceite Vegetal',
                'codigo' => 'INS010',
                'descripcion' => 'Aceite vegetal para cocinar',
                'costo_unitario' => 3.0000,
                'stock_actual' => 40.0000,
                'unidad_medida' => 'litro',
                'stock_minimo' => 10.0000,
                'activo' => true,
            ],
            [
                'nombre' => 'Queso Mozzarella',
                'codigo' => 'INS011',
                'descripcion' => 'Queso mozzarella para pizzas',
                'costo_unitario' => 15.0000,
                'stock_actual' => 12.0000,
                'unidad_medida' => 'kg',
                'stock_minimo' => 4.0000,
                'activo' => true,
            ],
            [
                'nombre' => 'Sal',
                'codigo' => 'INS012',
                'descripcion' => 'Sal de mesa refinada',
                'costo_unitario' => 0.5000,
                'stock_actual' => 50.0000,
                'unidad_medida' => 'kg',
                'stock_minimo' => 15.0000,
                'activo' => true,
            ],
        ];

        foreach ($insumos as $insumo) {
            Insumo::firstOrCreate(['codigo' => $insumo['codigo']], $insumo);
        }

        $this->command->info('Insumos de prueba creados exitosamente.');
    }
}
