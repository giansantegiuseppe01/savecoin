<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('abonos_credito')) {
            return;
        }

        // Cobros de deudas a crédito. Entran a la caja del turno en el que se cobraron.
        Schema::create('abonos_credito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_credito_id')->constrained('ventas_credito')->cascadeOnDelete();
            $table->foreignId('turno_id')->nullable()->constrained('turnos')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('monto', 12, 2);
            $table->decimal('tasa_cambio', 12, 4)->default(1);
            $table->string('forma_pago', 50);
            $table->string('referencia', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonos_credito');
    }
};
