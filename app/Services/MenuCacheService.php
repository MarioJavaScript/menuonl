<?php

namespace App\Services;

use App\Jobs\PurgarCacheRestauranteJob;
use App\Models\Restaurante;
use Illuminate\Support\Facades\Cache;

class MenuCacheService
{
    public const MENU_CACHE_PREFIX = 'menu';

    public const DEFAULT_TTL = 86400; // 24 hours

    /**
     * Retrieve cached menu data for a restaurant and language.
     *
     * @return array<string, mixed>|null
     */
    public function getMenu(int|Restaurante $restaurante, string $idioma): ?array
    {
        $id = $restaurante instanceof Restaurante ? $restaurante->id : $restaurante;

        return Cache::get("menu:{$id}:{$idioma}");
    }

    /**
     * Store menu data in L1 cache.
     *
     * @param  array<string, mixed>  $data
     */
    public function putMenu(int|Restaurante $restaurante, string $idioma, array $data, int $ttl = self::DEFAULT_TTL): void
    {
        $id = $restaurante instanceof Restaurante ? $restaurante->id : $restaurante;

        Cache::put("menu:{$id}:{$idioma}", $data, $ttl);
    }

    /**
     * Retrieve or compute menu data in L1 cache.
     *
     * @return array<string, mixed>
     */
    public function rememberMenu(int|Restaurante $restaurante, string $idioma, \Closure $callback, int $ttl = self::DEFAULT_TTL): array
    {
        $id = $restaurante instanceof Restaurante ? $restaurante->id : $restaurante;

        return Cache::remember("menu:{$id}:{$idioma}", $ttl, $callback);
    }

    /**
     * Invalidate all L1 cache keys for a restaurant and dispatch L2 Cloudflare purge.
     */
    public function invalidateRestaurante(int|Restaurante $restaurante): void
    {
        $model = $restaurante instanceof Restaurante
            ? $restaurante
            : Restaurante::withoutGlobalScopes()->find($restaurante);

        $id = $model ? $model->id : (int) $restaurante;
        $slug = $model ? $model->slug : null;

        // 1. Purge known language keys for this restaurant
        $idiomas = ['es', 'en'];
        if ($model && ! empty($model->idioma_default)) {
            $idiomas[] = $model->idioma_default;
        }

        if ($model) {
            try {
                if ($model->relationLoaded('idiomas')) {
                    $idiomas = array_merge($idiomas, $model->idiomas->pluck('idioma')->toArray());
                } else {
                    $idiomas = array_merge($idiomas, $model->idiomas()->pluck('idioma')->toArray());
                }
            } catch (\Throwable) {
                // Ignore relationship issues if table or records unavailable
            }
        }

        foreach (array_unique(array_filter($idiomas)) as $lang) {
            Cache::forget("menu:{$id}:{$lang}");
        }

        Cache::forget("restaurante:id:{$id}");
        if ($slug) {
            Cache::forget("restaurante:slug:{$slug}");
        }

        // 2. Dispatch async L2 Cloudflare purge
        PurgarCacheRestauranteJob::dispatch($id, $slug);
    }
}
