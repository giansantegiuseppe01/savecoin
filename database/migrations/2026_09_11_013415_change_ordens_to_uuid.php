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
            $table->dropForeign(['orden_id']);
        });

        Schema::table('bitacora_inventarios', function (Blueprint $table) {
            $table->dropForeign(['orden_id']);
        });

        Schema::table('ordens', function (Blueprint $table) {
            // Drop foreign key constraints
            $table->dropForeign(['user_id']);
            $table->dropForeign(['cliente_id']);

            // Change id to UUID
            $table->string('id')->change();

            // Re-add foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('set null');
        });

        Schema::table('detalle_ordens', function (Blueprint $table) {
            $table->string('orden_id')->change();
            $table->foreign('orden_id')->references('id')->on('ordens')->onDelete('cascade');
        });

        Schema::table('bitacora_inventarios', function (Blueprint $table) {
            $table->string('orden_id')->nullable()->change();
            $table->foreign('orden_id')->references('id')->on('ordens')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalle_ordens', function (Blueprint $table) {
            $table->dropForeign(['orden_id']);
        });

        Schema::table('bitacora_inventarios', function (Blueprint $table) {
            $table->dropForeign(['orden_id']);
        });

        Schema::table('ordens', function (Blueprint $table) {
            // Drop foreign key constraints
            $table->dropForeign(['user_id']);
            $table->dropForeign(['cliente_id']);

            // Revert id to bigInteger
            $table->bigIncrements('id')->change();

            // Re-add foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('set null');
        });

        Schema::table('detalle_ordens', function (Blueprint $table) {
            $table->bigInteger('orden_id')->change();
            $table->foreign('orden_id')->references('id')->on('ordens')->onDelete('cascade');
        });

        Schema::table('bitacora_inventarios', function (Blueprint $table) {
            $table->bigInteger('orden_id')->nullable()->change();
            $table->foreign('orden_id')->references('id')->on('ordens')->onDelete('cascade');
        });
    }
};
