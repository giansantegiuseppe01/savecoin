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
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo')->unique();
            $table->text('descripcion')->nullable();
            $table->decimal('precio_venta', 12, 4);
            $table->decimal('costo_produccion', 12, 4)->default(0);
            $table->string('categoria', 100)->nullable();
            $table->string('imagen_url')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('preparado_en_cocina')->default(true);
            $table->integer('tiempo_preparacion_minutos')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
