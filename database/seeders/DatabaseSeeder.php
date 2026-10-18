<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            CategoriaLibroContableSeeder::class,
        ]);

        // Datos de ejemplo solo fuera de producción
        if (! app()->isProduction()) {
            $this->call(InsumoSeeder::class);
        }
    }
}
