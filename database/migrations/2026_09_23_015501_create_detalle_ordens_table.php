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
        if (Schema::hasTable('detalle_ordens')) {
            return;
        }

        Schema::create('detalle_ordens', function (Blueprint $table) {
            $table->string('id')->primary(); // UUID generado por la app / el modelo
            $table->foreignId('orden_id')->constrained()->onDelete('cascade');
            $table->foreignId('producto_id')->constrained()->onDelete('cascade');
            $table->string('producto_nombre');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 12, 4);
            $table->decimal('subtotal', 12, 4);
            $table->decimal('descuento', 12, 4)->default(0);
            $table->decimal('total', 12, 4);
            $table->text('notas')->nullable();
            $table->boolean('fue_modificado')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->boolean('is_local')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_ordens');
    }
};
