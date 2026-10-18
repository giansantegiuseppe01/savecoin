<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * El correo y la contraseña del administrador se toman de ADMIN_EMAIL y
     * ADMIN_PASSWORD (.env). Si no se define la contraseña, se genera una
     * aleatoria que se muestra una sola vez: nunca se usa una clave fija.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@restaurant.com');
        $password = env('ADMIN_PASSWORD');
        $generada = false;

        if (! $password) {
            $password = Str::password(20, symbols: false);
            $generada = true;
        }

        $admin = User::where('email', $email)->first();

        if ($admin) {
            $this->command?->warn("El usuario {$email} ya existe: no se modifica su contraseña.");

            return;
        }

        User::create([
            'email' => $email,
            'nombre' => 'Admin',
            'apellido' => 'User',
            'password' => Hash::make($password),
            'rol' => 'admin',
        ]);

        $this->command?->info("Usuario admin creado: {$email}");

        if ($generada) {
            $this->command?->warn("Contraseña generada (guárdela ahora, no se volverá a mostrar): {$password}");
        }
    }
}
