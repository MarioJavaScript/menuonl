<?php

namespace App\Jobs;

use App\Services\SondaDeCola;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Probe job for menuonl:verificar-produccion: when a worker processes it, it leaves its mark in the cache.
 */
class LatidoColasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public string $marca) {}

    public function handle(): void
    {
        Cache::put(SondaDeCola::CLAVE, ['marca' => $this->marca, 'en' => now()->toIso8601String()], now()->addDay());
    }
}
