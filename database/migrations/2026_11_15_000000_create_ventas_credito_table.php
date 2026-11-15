<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ventas_credito')) {
            return;
        }

        // Ticket cerrado a crédito: el inventario ya se descontó y el cliente debe el saldo
        Schema::create('ventas_credito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->unique()->constrained('ordens')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->decimal('monto', 12, 2);
            $table->decimal('saldo', 12, 2);
            $table->string('estado', 20)->default('pendiente'); // pendiente | pagada
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_credito');
    }
};
