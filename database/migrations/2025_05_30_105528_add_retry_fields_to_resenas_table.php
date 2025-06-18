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
        Schema::table('resenas', function (Blueprint $table) {
            $table->integer('intentos_envio')->default(0)->after('enviado');
            $table->text('ultimo_error_envio')->nullable()->after('intentos_envio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resenas', function (Blueprint $table) {
            $table->dropColumn('intentos_envio');
            $table->dropColumn('ultimo_error_envio');
        });
    }
};
