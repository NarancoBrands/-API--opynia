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
        Schema::table('campanas', function (Blueprint $table) {
            $table->string('tipoBoton')->nullable()->after('estado'); // O la columna después de la que quieras añadirlo
            $table->string('textoBoton')->nullable()->after('tipoBoton');
            $table->string('urlBoton')->nullable()->after('textoBoton');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campanas', function (Blueprint $table) {
            $table->dropColumn('tipoBoton');
            $table->dropColumn('textoBoton');
            $table->dropColumn('urlBoton');
        });
    }
};
