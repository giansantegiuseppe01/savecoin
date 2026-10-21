<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordens', function (Blueprint $table) {
            if (! Schema::hasColumn('ordens', 'referencia_pago')) {
                // Referencia bancaria de las transferencias / pagos electrónicos
                $table->string('referencia_pago', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordens', function (Blueprint $table) {
            if (Schema::hasColumn('ordens', 'referencia_pago')) {
                $table->dropColumn('referencia_pago');
            }
        });
    }
};
