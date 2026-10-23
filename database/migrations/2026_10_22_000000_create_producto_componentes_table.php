<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Combos: un producto formado por otros productos (cada uno con su cantidad).
     * Al vender el combo se descuentan los insumos de cada producto incluido.
     */
    public function up(): void
    {
        Schema::create('producto_componentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->foreignId('componente_id')->constrained('productos')->onDelete('restrict');
            $table->decimal('cantidad', 12, 4)->default(1);
            $table->timestamps();

            $table->unique(['producto_id', 'componente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_componentes');
    }
};
