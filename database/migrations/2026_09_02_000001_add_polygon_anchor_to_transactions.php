<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Transaction_tb', function (Blueprint $table) {
            $table->string('Chain_network', 40)->nullable()->after('T_type');
            $table->string('Chain_payload_hash', 66)->nullable()->unique()->after('Chain_network');
            $table->string('Chain_tx_hash', 66)->nullable()->unique()->after('Chain_payload_hash');
            $table->unsignedBigInteger('Chain_block_number')->nullable()->after('Chain_tx_hash');
            $table->string('Chain_status', 20)->nullable()->index()->after('Chain_block_number');
            $table->text('Chain_error')->nullable()->after('Chain_status');
            $table->timestamp('Chain_confirmed_at')->nullable()->after('Chain_error');
        });
    }

    public function down(): void
    {
        Schema::table('Transaction_tb', function (Blueprint $table) {
            $table->dropColumn([
                'Chain_network', 'Chain_payload_hash', 'Chain_tx_hash', 'Chain_block_number',
                'Chain_status', 'Chain_error', 'Chain_confirmed_at',
            ]);
        });
    }
};
