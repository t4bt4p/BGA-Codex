<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('rentals:suspend-overdue', function () {
    $count = app(\App\Services\OverdueAccountService::class)->suspendOverdueAccounts();
    $this->info("Suspended {$count} overdue accounts.");
})->purpose('Suspend accounts with unreturned overdue boardgames');

Schedule::command('rentals:suspend-overdue')->everyMinute()->withoutOverlapping();
