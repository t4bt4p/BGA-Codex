<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('User_tb', function (Blueprint $table) {
            $table->string('User_role', 20)->default('user')->after('User_status');
        });
        Schema::create('Rental_tb', function (Blueprint $table) {
            $table->increments('Rental_id');
            $table->unsignedInteger('User_id');
            $table->unsignedInteger('Bg_id');
            $table->unsignedInteger('Rental_cost');
            $table->timestamp('rented_at');
            $table->timestamp('due_at');
            $table->timestamp('returned_at')->nullable();
            $table->unsignedInteger('late_fee')->default(0);
            $table->string('Rental_status', 20)->default('active');
            $table->timestamps();
            $table->foreign('User_id')->references('User_id')->on('User_tb');
            $table->foreign('Bg_id')->references('Bg_id')->on('Boardgame_tb');
        });
        Schema::create('Topup_request_tb', function (Blueprint $table) {
            $table->increments('Topup_id');
            $table->unsignedInteger('User_id');
            $table->unsignedInteger('Amount');
            $table->string('Reference', 40)->unique();
            $table->string('Status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamps();
            $table->foreign('User_id')->references('User_id')->on('User_tb');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('Topup_request_tb');
        Schema::dropIfExists('Rental_tb');
        Schema::table('User_tb', fn (Blueprint $table) => $table->dropColumn('User_role'));
    }
};
