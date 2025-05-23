<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('comercios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('direccion');
            $table->string('telefono');
            $table->string('email')->nullable();
            $table->integer('horas');
            $table->integer('minutos');
            $table->unsignedBigInteger('campana')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('bloqueado')->default(false);
            $table->string('cuenta');
            $table->timestamps();
        });

    }

    public function down()
    {
        Schema::dropIfExists('comercios');
    }
};
