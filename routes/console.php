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

// Revisa más tarde que el respaldo de arriba exista: también avisa si el cron dejó de correr (un respaldo que nunca arrancó no falla).
Schedule::command('menuonl:backup-alerta')
    ->dailyAt(RespaldoService::horaDeAlerta())
    ->timezone(RespaldoService::zonaHorariaProgramada())
    ->withoutOverlapping();

Schedule::command('menuonl:purgar-escaneos')
    ->dailyAt('04:30')
    ->timezone(RespaldoService::zonaHorariaProgramada())
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('menuonl:purgar-escaneos - El scheduler registró una purga fallida.'));

Schedule::command('menuonl:purgar-reserva-eventos')
    ->dailyAt('04:50')
    ->timezone(RespaldoService::zonaHorariaProgramada())
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('menuonl:purgar-reserva-eventos - El scheduler registró una purga fallida.'));
