<?php

namespace App\Models\Scopes;

use App\Models\Restaurante;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class RestauranteScope implements Scope
{
    /**
     * Active restaurant context ID for the current session/request.
     */
    protected static ?int $currentRestauranteId = null;

    /**
     * Set the current restaurant context ID.
     */
    public static function setRestauranteId(?int $id): void
    {
        static::$currentRestauranteId = $id;
    }

    /**
     * Get the current restaurant context ID.
     */
    public static function getRestauranteId(): ?int
    {
        return static::$currentRestauranteId;
    }

    /**
     * Resolve the restaurant that queries must be limited to.
     *
     * An explicit context always wins. During panel requests the Filament tenant is used.
     * Outside the panel (public routes, console, seeders, queues) null means "no filter".
     */
    public static function resolveRestauranteId(): ?int
    {
        if (static::$currentRestauranteId !== null) {
            return static::$currentRestauranteId;
        }

        if (static::isPanelRequest()) {
            $tenant = Filament::getTenant();

            return $tenant instanceof Restaurante ? $tenant->getKey() : null;
        }

        return null;
    }

    /**
     * Determine if the current request is being served by a Filament panel.
     */
    public static function isPanelRequest(): bool
    {
        return Filament::isServing();
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * Panel requests without a resolved restaurant fail closed and return no rows.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $restauranteId = static::resolveRestauranteId();

        if ($restauranteId !== null) {
            $builder->where($model->qualifyColumn('restaurante_id'), $restauranteId);

            return;
        }

        if (static::isPanelRequest()) {
            $builder->whereRaw('1 = 0');
        }
    }
}
