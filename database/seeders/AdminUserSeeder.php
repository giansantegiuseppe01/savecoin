<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@restaurant.com'],
            [
                'nombre' => 'Admin',
                'apellido' => 'User',
                'password' => Hash::make('password'),
                'rol' => 'admin',
            ]
        );

        $this->command->info('Usuario admin creado: admin@restaurant.com / password');
    }
}
