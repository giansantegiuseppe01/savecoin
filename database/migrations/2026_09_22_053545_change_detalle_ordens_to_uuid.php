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
            // Drop foreign key constraint
            $table->dropForeign(['orden_id']);
            $table->dropForeign(['producto_id']);

            // Change id to UUID
            $table->string('id')->change();

            // Re-add foreign keys
            $table->foreign('orden_id')->references('id')->on('ordens')->onDelete('cascade');
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalle_ordens', function (Blueprint $table) {
            // Drop foreign key constraints
            $table->dropForeign(['orden_id']);
            $table->dropForeign(['producto_id']);

            // Revert id to bigInteger
            $table->bigIncrements('id')->change();

            // Re-add foreign keys
            $table->foreign('orden_id')->references('id')->on('ordens')->onDelete('cascade');
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');
        });
    }
};
