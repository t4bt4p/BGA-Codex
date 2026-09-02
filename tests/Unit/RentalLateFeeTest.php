<?php

namespace Tests\Unit;

use App\Models\Rental_tb;
use Carbon\Carbon;
use Tests\TestCase;

class RentalLateFeeTest extends TestCase
{
    public function test_no_fee_is_charged_before_or_at_the_due_time(): void
    {
        $rental = $this->rentalDueAt('2026-09-10 12:00:00');

        $this->assertSame(0, $rental->calculateOverdueDays(Carbon::parse('2026-09-10 12:00:00')));
        $this->assertSame(0, $rental->calculateLateFee(Carbon::parse('2026-09-10 11:59:59')));
    }

    public function test_each_started_overdue_day_costs_one_full_rental_price(): void
    {
        $rental = $this->rentalDueAt('2026-09-10 12:00:00');

        $this->assertSame(30, $rental->calculateLateFee(Carbon::parse('2026-09-10 12:00:01')));
        $this->assertSame(30, $rental->calculateLateFee(Carbon::parse('2026-09-11 12:00:00')));
        $this->assertSame(60, $rental->calculateLateFee(Carbon::parse('2026-09-11 12:00:01')));
    }

    private function rentalDueAt(string $dueAt): Rental_tb
    {
        return new Rental_tb([
            'Rental_cost' => 150,
            'rental_days' => 5,
            'daily_rate' => 30,
            'due_at' => Carbon::parse($dueAt),
            'Rental_status' => 'active',
        ]);
    }
}
