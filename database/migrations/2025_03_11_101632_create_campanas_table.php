<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {


        Schema::create('campanas', function (Blueprint $table) {
            $table->id();
            $table->text('tituloCampana');
            $table->text('mensajeCampana');
            $table->integer('idComercio');
            $table->string('imagenCampana')->nullable();
            $table->integer('horasCampana');
            $table->integer('minutosCampana');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('campanas');

    }
}
;
