<?php

namespace App\Traits;

use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToRestaurante
{
    /**
     * Boot the trait to attach the global scope and auto-assign restaurante_id.
     */
    protected static function bootBelongsToRestaurante(): void
    {
        static::addGlobalScope(new RestauranteScope);

        static::creating(function ($model) {
            if (empty($model->restaurante_id) && RestauranteScope::getRestauranteId() !== null) {
                $model->restaurante_id = RestauranteScope::getRestauranteId();
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
