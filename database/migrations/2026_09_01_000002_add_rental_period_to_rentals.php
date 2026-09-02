<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('Rental_tb', function (Blueprint $table) {
            $table->unsignedTinyInteger('rental_days')->default(1)->after('Rental_cost');
            $table->unsignedInteger('daily_rate')->nullable()->after('rental_days');
        });
    }

    public function down(): void
    {
        Schema::table('Rental_tb', function (Blueprint $table) {
            $table->dropColumn(['rental_days', 'daily_rate']);
        });
    }
};
