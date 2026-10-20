<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El modelo Orden y SyncController usan `synced_at` e `is_local`, pero ninguna
 * migración los creaba en `ordens` (solo en `detalle_ordens`). Las bases que ya
 * los tienen (añadidos a mano) no se tocan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordens', function (Blueprint $table) {
            if (! Schema::hasColumn('ordens', 'synced_at')) {
                $table->timestamp('synced_at')->nullable();
            }
            if (! Schema::hasColumn('ordens', 'is_local')) {
                $table->boolean('is_local')->default(false);
            }
        });
    }

    public function down(): void
    {
        // No se eliminan: pueden existir en bases que ya los tenían antes de esta migración.
    }
};
