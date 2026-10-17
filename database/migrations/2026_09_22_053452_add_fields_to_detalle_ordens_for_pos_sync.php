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
        Schema::table('detalle_ordens', function (Blueprint $table) {
            // Agregar campos para sincronización POS móvil
            $table->string('producto_nombre')->after('producto_id');
            $table->boolean('fue_modificado')->default(false)->after('notas');
            $table->timestamp('synced_at')->nullable()->after('fue_modificado');
            $table->boolean('is_local')->default(false)->after('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalle_ordens', function (Blueprint $table) {
            $table->dropColumn(['producto_nombre', 'fue_modificado', 'synced_at', 'is_local']);
        });
    }
};
