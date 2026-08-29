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
        Schema::create('Boardgame_tb', function (Blueprint $table) {
            $table->increments('Bg_id');
            $table->string('Bg_name', 255);
            $table->integer('Bg_cost');
            $table->integer('Bg_min_player');
            $table->integer('Bg_max_player');
            $table->integer('Bg_playduration');
            $table->unsignedInteger('Bg_Catetogory_id');
            $table->tinyInteger('Bg_use_status');
            $table->text('Bg_Image');
            $table->timestamps();

            $table->foreign('Bg_Catetogory_id')->references('Bg_category_id')->on('Boardgame_category_tb');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Boardgame_tb');
    }
};
