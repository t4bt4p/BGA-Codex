<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('User_tb', fn (Blueprint $table) => $table->string('User_phone', 15)->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('User_tb', fn (Blueprint $table) => $table->char('User_phone', 10)->nullable(false)->change());
    }
};
