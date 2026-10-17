<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ordens', function (Blueprint $table) {
            // Agregar campo aplica_iva
            $table->boolean('aplica_iva')->default(true)->after('tasa_cambio');

            // Modificar el enum de estado para incluir 'abierta', 'cerrada', 'anulada'
            $table->dropColumn('estado');
        });

        Schema::table('ordens', function (Blueprint $table) {
            $table->enum('estado', ['abierta', 'cerrada', 'anulada'])->default('abierta')->after('metodo_pago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordens', function (Blueprint $table) {
            $table->dropColumn('aplica_iva');
            $table->dropColumn('estado');
        });

        Schema::table('ordens', function (Blueprint $table) {
            $table->enum('estado', ['activa', 'completada', 'anulada'])->default('completada')->after('metodo_pago');
        });
    }
};
