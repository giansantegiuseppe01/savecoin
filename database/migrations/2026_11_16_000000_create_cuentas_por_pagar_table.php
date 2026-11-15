<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cuentas_por_pagar')) {
            return;
        }

        // Lo que el negocio debe a proveedores o acreedores (facturas y préstamos)
        Schema::create('cuentas_por_pagar', function (Blueprint $table) {
            $table->id();
            $table->string('acreedor', 150);
            $table->string('tipo', 20)->default('factura'); // factura | prestamo
            $table->string('concepto', 255)->nullable();
            $table->string('numero_documento', 100)->nullable();
            $table->decimal('monto', 12, 2);
            $table->decimal('saldo', 12, 2);
            $table->string('estado', 20)->default('pendiente'); // pendiente | pagada
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_por_pagar');
    }
};
