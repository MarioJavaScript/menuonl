<?php

namespace App\Traits;

use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToRestaurante
{
    /**
     * Boot the trait to attach the global scope, auto-assign restaurante_id
     * and reject writes that escape the active restaurant context.
     */
    protected static function bootBelongsToRestaurante(): void
    {
        static::addGlobalScope(new RestauranteScope);

        static::creating(function (Model $model): void {
            $restauranteId = RestauranteScope::resolveRestauranteId();

            if (empty($model->restaurante_id)) {
                if ($restauranteId !== null) {
                    $model->restaurante_id = $restauranteId;
                } elseif (RestauranteScope::isPanelRequest()) {
                    throw new AuthorizationException('No hay un restaurante activo para crear este registro.');
                }

                return;
            }

            if ($restauranteId !== null && (int) $model->restaurante_id !== $restauranteId) {
                throw new AuthorizationException('No se puede crear un registro para otro restaurante.');
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('restaurante_id') && RestauranteScope::resolveRestauranteId() !== null) {
                throw new AuthorizationException('No se puede mover un registro a otro restaurante.');
            }
        });
    }

    /**
     * Restaurant relationship.
     */
    public function restaurante(): BelongsTo
    {
        return $this->belongsTo(Restaurante::class, 'restaurante_id');
    }
}
