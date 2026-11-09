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
        // Instalaciones existentes: la tabla ya está creada con este mismo esquema.
        if (Schema::hasTable('ordens')) {
            return;
        }

        Schema::create('ordens', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recibo')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Empleado
            $table->foreignId('turno_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->onDelete('set null');
            $table->string('cliente_nombre')->nullable();
            $table->enum('tipo', ['venta', 'reembolso'])->default('venta');
            $table->decimal('subtotal', 12, 4)->default(0);
            $table->decimal('descuento', 12, 4)->default(0);
            $table->decimal('impuesto', 12, 4)->default(0);
            $table->decimal('total', 12, 4)->default(0);
            $table->decimal('tasa_cambio', 12, 4)->default(1.0000);
            $table->boolean('aplica_iva')->default(true);
            $table->decimal('costo_insumos', 12, 4)->default(0);
            $table->decimal('beneficio_bruto', 12, 4)->default(0);
            $table->enum('metodo_pago', ['efectivo', 'tarjeta', 'transferencia', 'mixto'])->nullable();
            $table->string('referencia_pago', 100)->nullable(); // Referencia bancaria de transferencias / pagos electrónicos
            $table->enum('estado', ['abierta', 'cerrada', 'anulada'])->default('abierta');
            $table->string('turno', 50)->nullable(); // mañana, tarde, noche
            $table->timestamp('fecha_hora');
            $table->text('notas')->nullable();
            $table->string('recibo_anulado_id')->nullable(); // Referencia al recibo original si es reembolso
            $table->timestamp('synced_at')->nullable();
            $table->boolean('is_local')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordens');
    }
};
