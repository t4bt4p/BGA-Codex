<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('Topup_request_tb', 'approved_by')) {
            Schema::table('Topup_request_tb', function (Blueprint $table) {
                $table->dropColumn('approved_by');
            });
        }
    }

    public function down(): void
    {
        Schema::table('Topup_request_tb', function (Blueprint $table) {
            $table->unsignedInteger('approved_by')->nullable();
        });
    }
};
