<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('formas_pago')) {
            return;
        }

        // Formas de pago de la empresa (p. ej. Pago Móvil) que se imprimen al final de la pre-cuenta
        Schema::create('formas_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->text('datos'); // una línea por dato: banco, teléfono, cédula...
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formas_pago');
    }
};
