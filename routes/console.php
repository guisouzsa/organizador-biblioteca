<?php

use Illuminate\Support\Facades\Schedule;

// Expira retenções de reserva vencidas. Em desenvolvimento: php artisan schedule:work
Schedule::command('library:expire-reservations')->everyTenMinutes()->withoutOverlapping();
