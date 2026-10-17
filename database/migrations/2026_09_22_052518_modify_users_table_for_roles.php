<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Agregar columnas nombre y apellido como nullable primero
            $table->string('nombre')->nullable()->after('id');
            $table->string('apellido')->nullable()->after('nombre');
        });

        // Migrar datos existentes de name a nombre
        DB::statement('UPDATE users SET nombre = name WHERE name IS NOT NULL');
        DB::statement("UPDATE users SET apellido = '' WHERE apellido IS NULL");

        Schema::table('users', function (Blueprint $table) {
            // Hacer las columnas not null después de migrar datos
            $table->string('nombre')->nullable(false)->change();
            $table->string('apellido')->nullable(false)->change();

            // Eliminar columna name antigua
            $table->dropColumn('name');
        });

        // Modificar el enum de rol para eliminar 'cliente' (PostgreSQL)
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_rol_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_rol_check CHECK (rol IN ('admin', 'empleado'))");
        DB::statement("ALTER TABLE users ALTER COLUMN rol SET DEFAULT 'empleado'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Restaurar columna name
            $table->string('name')->nullable()->after('id');
        });

        // Migrar datos de nombre a name
        DB::statement('UPDATE users SET name = nombre WHERE nombre IS NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            // Hacer name not null
            $table->string('name')->nullable(false)->change();

            // Eliminar columnas nombre y apellido
            $table->dropColumn(['nombre', 'apellido']);
        });

        // Restaurar el enum de rol con 'cliente' (PostgreSQL)
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_rol_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_rol_check CHECK (rol IN ('admin', 'empleado', 'cliente'))");
        DB::statement("ALTER TABLE users ALTER COLUMN rol SET DEFAULT 'cliente'");
    }
};
