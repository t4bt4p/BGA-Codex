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
        // 📌 เพิ่มบรรทัดนี้: บังคับลบตารางเก่าทิ้งก่อนถ้ามีอยู่แล้ว
        Schema::dropIfExists('Transaction_tb');

        // จากนั้นค่อยสร้างตารางใหม่
        Schema::create('Transaction_tb', function (Blueprint $table) {
            $table->increments('Ts_id');
            $table->unsignedInteger('User_id');
            
            $table->unsignedInteger('Bg_id')->nullable(); // อนุญาตให้เป็น null
            
            $table->integer('T_cost');
            $table->string('T_type', 255);
            $table->timestamps(); 

            $table->foreign('User_id')->references('User_id')->on('User_tb');
            $table->foreign('Bg_id')->references('Bg_id')->on('Boardgame_tb');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Transaction_tb');
    }
};