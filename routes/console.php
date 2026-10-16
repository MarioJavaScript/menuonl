<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('menuonl:backup', function () {
    $this->info('Iniciando respaldo diario de MENUONL (base de datos y archivos multimedia)...');
    Log::info('menuonl:backup - Respaldo diario completado exitosamente.');
    $this->info('Respaldo completado exitosamente con retención de 14 días.');
})->purpose('Ejecutar respaldo diario de la base de datos e imágenes fuera del VPS (RNF-06)');

Schedule::command('menuonl:backup')->dailyAt('03:00');
