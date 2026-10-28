<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('Transaction_tb', 'Rental_id')) {
            Schema::table('Transaction_tb', function (Blueprint $table) {
                $table->unsignedInteger('Rental_id')->nullable()->after('Bg_id')->index();
            });
        } else {
            // รองรับกรณี migration เคยหยุดหลังสร้างคอลัมน์ แต่ก่อนสร้าง foreign key
            Schema::table('Transaction_tb', function (Blueprint $table) {
                $table->unsignedInteger('Rental_id')->nullable()->change();
            });
        }

        Schema::table('Transaction_tb', function (Blueprint $table) {
            $table->foreign('Rental_id')->references('Rental_id')->on('Rental_tb')->nullOnDelete();
        });

        $rentals = DB::table('Rental_tb')->get()->groupBy(
            fn ($rental) => $rental->User_id.':'.$rental->Bg_id
        );

        DB::table('Transaction_tb')
            ->whereIn('T_type', ['rental_debit', 'return_event', 'late_fee_debit'])
            ->orderBy('Ts_id')
            ->get()
            ->each(function ($transaction) use ($rentals) {
                $matches = $rentals->get($transaction->User_id.':'.$transaction->Bg_id, collect());
                if ($matches->isEmpty()) {
                    return;
                }

                $transactionTime = strtotime($transaction->created_at);
                $rental = $matches->sortBy(function ($rental) use ($transaction, $transactionTime) {
                    $reference = $transaction->T_type === 'rental_debit'
                        ? $rental->rented_at
                        : ($rental->returned_at ?: $rental->rented_at);

                    return abs(strtotime($reference) - $transactionTime);
                })->first();

                DB::table('Transaction_tb')
                    ->where('Ts_id', $transaction->Ts_id)
                    ->update(['Rental_id' => $rental->Rental_id]);
            });
    }

    public function down(): void
    {
        Schema::table('Transaction_tb', function (Blueprint $table) {
            $table->dropForeign(['Rental_id']);
            $table->dropColumn('Rental_id');
        });
    }
};
