<?php

namespace App\Models\Scopes;

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
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (static::$currentRestauranteId !== null) {
            $builder->where($model->getTable().'.restaurante_id', static::$currentRestauranteId);
        }
    }
}
