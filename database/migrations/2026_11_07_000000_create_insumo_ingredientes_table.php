<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Insumos preparados (p. ej. una salsa hecha con otros insumos). La receta indica cuánto de
     * cada ingrediente lleva un lote; "rendimiento" es cuánto produce ese lote.
     */
    public function up(): void
    {
        if (Schema::hasTable('insumo_ingredientes')) {
            return;
        }

        Schema::create('insumo_ingredientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insumo_id')->constrained('insumos')->onDelete('cascade');
            $table->foreignId('ingrediente_id')->constrained('insumos')->onDelete('restrict');
            $table->decimal('cantidad', 12, 4);
            $table->timestamps();

            $table->unique(['insumo_id', 'ingrediente_id']);
        });

        // Solo instalaciones anteriores a la unificación (ya vienen con la columna en create_insumos)
        if (! Schema::hasColumn('insumos', 'rendimiento')) {
            Schema::table('insumos', function (Blueprint $table) {
                $table->decimal('rendimiento', 12, 4)->nullable()->after('stock_minimo');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('insumo_ingredientes');
    }
};
