<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSearchlyticsTable extends Migration
{

    public function up()
    {
        Schema::create('Searchlytics', function(Blueprint $table) {
            $table->increments('id');
            $table->string('query');
            $table->integer('user_id')->nullable();
            $table->timestamps();
            // Schema declaration
            // Constraints declaration

        });
    }

    public function down()
    {
        Schema::drop('Searchlytics');
    }
}
