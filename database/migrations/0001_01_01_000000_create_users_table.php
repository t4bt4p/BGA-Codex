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
        Schema::create('User_tb', function (Blueprint $table) {
            $table->increments('User_id'); // INT PK
            $table->string('User_username', 255)->unique(); // เพิ่ม ->unique() ให้กับ Username เพื่อไม่ให้สมัครซ้ำ
            $table->string('User_password', 255);
            $table->char('User_phone', 10);
            $table->string('User_name', 255);

            // --- ส่วนที่ปรับปรุง ---
            // 1. Wallet_id ควรเป็น nullable เผื่อว่าตอนสมัครยังไม่มี Wallet
            $table->unsignedInteger('Wallet_id')->nullable();

            // 2. User_status ควรมีค่าเริ่มต้น (เช่น 1 = ใช้งานปกติ)
            $table->tinyInteger('User_status')->default(1);
            // ------------------------

            // สิ่งที่ Laravel ต้องการสำหรับระบบ Auth (ควรมีไว้ แม้ ER Diagram จะไม่ได้บอก)
            $table->rememberToken();

            // Timestamps ที่เราตกลงกันไว้
            $table->timestamps();

            // Foreign Key
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('User_tb'); 
    }
};
