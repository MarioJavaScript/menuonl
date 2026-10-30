<?php

namespace App\Traits;

use App\Services\MenuCacheService;

/**
 * Para los datos del menú público que no pasan por Auditable: traducciones, idiomas del restaurante y etiquetas de un platillo.
 *
 * El panel los guarda como relaciones (repeater o select de Filament) sin tocar al registro padre, así que el observer de
 * auditoría nunca se entera. Al crear, cambiar o borrar uno, se vacía la caché del menú de su restaurante (L1 y purga de L2).
 *
 * El modelo que lo usa implementa restauranteIdDelMenu().
 */
trait InvalidaElMenuDelRestaurante
{
    /**
     * Restaurante cuyo menú muestra este registro (null si ya no se puede resolver).
     */
    abstract public function restauranteIdDelMenu(): ?int;

    public static function bootInvalidaElMenuDelRestaurante(): void
    {
        static::saved(function (self $modelo): void {
            if ($modelo->wasRecentlyCreated || $modelo->wasChanged()) {
                $modelo->invalidarMenuDelRestaurante();
            }
        });

        static::deleted(fn (self $modelo) => $modelo->invalidarMenuDelRestaurante());
    }

    public function invalidarMenuDelRestaurante(): void
    {
        $restauranteId = $this->restauranteIdDelMenu();

        if ($restauranteId !== null) {
            app(MenuCacheService::class)->invalidateRestaurante($restauranteId);
        }
    }
}
