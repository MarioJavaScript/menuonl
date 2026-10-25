<?php

use App\Services\RespaldoService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('menuonl:backup')
    ->dailyAt(RespaldoService::horaProgramada())
    ->timezone(RespaldoService::zonaHorariaProgramada())
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('menuonl:backup - El scheduler registró un respaldo fallido (RNF-06).'));
