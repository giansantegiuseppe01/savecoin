<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pagos_por_pagar')) {
            return;
        }

        // Pagos hechos a una cuenta por pagar (abonos)
        Schema::create('pagos_por_pagar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_por_pagar_id')->constrained('cuentas_por_pagar')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->string('forma_pago', 50);
            $table->string('referencia', 100)->nullable();
            $table->date('fecha');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_por_pagar');
    }
};
