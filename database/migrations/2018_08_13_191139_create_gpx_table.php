<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGpxTable extends Migration
{

    public function up()
    {
        Schema::create('gpx', function(Blueprint $table) {
          $table->increments('id');
          $table->integer('user_id')->unsigned();
          $table->string('lon')->nullable();
          $table->string('lat')->nullable();
          $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
          $table->timestamps();
            // Schema declaration
            // Constraints declaration

        });
    }

    public function down()
    {
        Schema::drop('gpx');
    }
}
