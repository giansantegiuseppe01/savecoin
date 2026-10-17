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
        Schema::create('bitacora_inventarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insumo_id')->constrained()->onDelete('cascade');
            $table->foreignId('orden_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('tipo_movimiento', ['entrada', 'salida', 'ajuste', 'reembolso']);
            $table->decimal('cantidad_anterior', 12, 4);
            $table->decimal('cantidad_movimiento', 12, 4);
            $table->decimal('cantidad_nueva', 12, 4);
            $table->string('motivo', 255);
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Usuario que realizó el movimiento
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitacora_inventarios');
    }
};
