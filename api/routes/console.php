<?php

use App\Jobs\SyncExchangeRates;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncExchangeRates)
    ->dailyAt('18:15')
    ->onOneServer()
    ->withoutOverlapping(30);
