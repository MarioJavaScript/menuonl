<?php

namespace App\Models;

use App\Traits\BelongsToRestaurante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestauranteIdioma extends Model
{
    use BelongsToRestaurante, HasFactory;

    protected $table = 'restaurante_idiomas';

    protected $fillable = [
        'restaurante_id',
        'codigo',
        'idioma',
        'orden',
        'activo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * Scope to active languages.
     */
    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Scope ordered by display position.
     */
    public function scopeOrdenado(Builder $query): Builder
    {
        return $query->orderBy('orden');
    }

    /**
     * Keep 'codigo' and 'idioma' in sync.
     */
    protected function codigo(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?? $this->attributes['idioma'] ?? null,
            set: fn (?string $value) => [
                'codigo' => $value,
                'idioma' => $this->attributes['idioma'] ?? $value,
            ]
        );
    }

    /**
     * Keep 'idioma' and 'codigo' in sync.
     */
    protected function idioma(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?? $this->attributes['codigo'] ?? null,
            set: fn (?string $value) => [
                'idioma' => $value,
                'codigo' => $this->attributes['codigo'] ?? $value,
            ]
        );
    }
}
