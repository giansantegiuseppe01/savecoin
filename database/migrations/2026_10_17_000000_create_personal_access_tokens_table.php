<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de tokens de Laravel Sanctum (usada por el login de la API / app móvil).
     *
     * Sanctum 4 ya no carga esta migración automáticamente y el repositorio no la
     * incluía: en una instalación nueva, POST /api/login fallaba con
     * "no such table: personal_access_tokens". Se crea solo si no existe, para no
     * afectar a bases de datos que ya la tienen.
     */
    public function up(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // No se elimina la tabla: pudo existir antes de esta migración.
    }
};
