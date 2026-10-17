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
        Schema::create('libro_contable', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->enum('tipo_movimiento', ['entrada', 'salida']);
            $table->decimal('monto', 12, 4);
            $table->string('descripcion')->nullable();
            $table->decimal('saldo_despues', 12, 4);
            $table->string('referencia')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('libro_contable');
    }
};
