<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PurgarCacheRestauranteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $restauranteId,
        public ?string $slug = null
    ) {}

    /**
     * Execute the job to purge Cloudflare L2 edge cache.
     */
    public function handle(): void
    {
        $zoneId = config('services.cloudflare.zone_id');
        $token = config('services.cloudflare.api_token');
        $domain = config('services.cloudflare.domain', 'menuonl.com');

        if (empty($zoneId) || empty($token)) {
            Log::info("PurgarCacheRestauranteJob: Cloudflare no configurado. Omitiendo purga para restaurante {$this->restauranteId} ({$this->slug}).");

            return;
        }

        if (empty($this->slug)) {
            Log::warning("PurgarCacheRestauranteJob: Slug no disponible para restaurante {$this->restauranteId}. Omitiendo purga.");

            return;
        }

        $url = "https://{$domain}/m/{$this->slug}";

        try {
            $response = Http::withToken($token)
                ->timeout(5)
                ->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache", [
                    'files' => [
                        $url,
                    ],
                ]);

            if ($response->failed()) {
                Log::error("PurgarCacheRestauranteJob: Error al purgar Cloudflare para {$this->slug}: ".$response->body());
            }
        } catch (\Throwable $e) {
            Log::error("PurgarCacheRestauranteJob: Excepción al purgar Cloudflare para {$this->slug}: ".$e->getMessage());
        }
    }
}
