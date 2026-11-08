<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro de acciones sensibles (eliminar/anular/reembolsar recibos...): quién, cuándo,
     * por qué y una copia de lo que había, por si hay que revisarlo después.
     */
    public function up(): void
    {
        Schema::create('bitacora_acciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 50)->index();
            $table->string('entidad', 50)->nullable();
            $table->string('entidad_id', 64)->nullable();
            $table->text('descripcion');
            $table->text('motivo')->nullable();
            $table->json('datos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_acciones');
    }
};
