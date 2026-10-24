<?php

namespace App\Policies\Concerns;

use App\Enums\Capacidad;
use App\Enums\PlanRestaurante;
use App\Models\Restaurante;
use Filament\Facades\Filament;

/**
 * Lee el plan del restaurante para las políticas (docs/planes.md, 2.3). Reutiliza el restaurante activo del panel
 * cuando es el mismo, así el caso común no hace consultas extra.
 */
trait ConsultaElPlanDelRestaurante
{
    /**
     * Un restaurante que no existe cuenta como el plan más restrictivo (D12).
     */
    protected function planDe(?int $restauranteId): PlanRestaurante
    {
        if ($restauranteId === null) {
            return PlanRestaurante::SoloMenu;
        }

        $tenant = Filament::getTenant();

        if ($tenant instanceof Restaurante && (int) $tenant->getKey() === $restauranteId) {
            return $tenant->plan;
        }

        return Restaurante::query()->whereKey($restauranteId)->first(['id', 'plan'])?->plan ?? PlanRestaurante::SoloMenu;
    }

    protected function planIncluye(?int $restauranteId, Capacidad $capacidad): bool
    {
        return $this->planDe($restauranteId)->incluye($capacidad);
    }
}
