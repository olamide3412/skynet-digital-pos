<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('commerce:expire-reservations')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('app:database-back-up')
    ->dailyAt('02:00');
