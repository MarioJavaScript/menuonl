<?php

namespace App\Services;

use App\Jobs\LatidoColasJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Checks that a queue worker is really processing jobs: it queues a probe job and waits for its mark in the cache.
 *
 * The cache must be shared between processes (database, file, redis...); with the array driver the worker could never
 * leave its mark where this process can read it.
 */
class SondaDeCola
{
    public const string CLAVE = 'menuonl:cola:latido';

    /**
     * Queue a probe job and return its mark.
     */
    public function iniciar(): string
    {
        $marca = (string) Str::uuid();

        LatidoColasJob::dispatch($marca);

        return $marca;
    }

    /**
     * Whether a worker already processed the probe job with this mark.
     */
    public function confirmado(string $marca): bool
    {
        $latido = Cache::get(self::CLAVE);

        return is_array($latido) && ($latido['marca'] ?? null) === $marca;
    }

    /**
     * Queue a probe job and wait up to $segundos for a worker to process it.
     */
    public function sondear(int $segundos): bool
    {
        $marca = $this->iniciar();
        $limite = microtime(true) + max(0, $segundos);

        do {
            if ($this->confirmado($marca)) {
                return true;
            }

            usleep(500_000);
        } while (microtime(true) < $limite);

        return $this->confirmado($marca);
    }

    /**
     * Whether the cache store can carry the mark from the worker process to this one.
     */
    public function cacheCompartida(): bool
    {
        return ! in_array(config('cache.default'), ['array', 'null'], true);
    }
}
