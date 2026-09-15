<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('Rental_tb', 'late_fee')) {
            Schema::table('Rental_tb', function (Blueprint $table) {
                $table->dropColumn('late_fee');
            });
        }
    }

    public function down(): void
    {
        Schema::table('Rental_tb', function (Blueprint $table) {
            $table->unsignedInteger('late_fee')->default(0);
        });
    }
};
