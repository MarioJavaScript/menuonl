<?php

namespace App\Models;

use App\Models\Concerns\HasTraducciones;
use App\Traits\Auditable;
use App\Traits\BelongsToRestaurante;
use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use Auditable, BelongsToRestaurante, HasFactory, HasTraducciones, SoftDeletes;

    protected $table = 'categorias';

    protected $fillable = [
        'restaurante_id',
        'orden',
        'visible',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'visible' => 'boolean',
        ];
    }

    /**
     * Translations of the category name and description.
     */
    public function traducciones(): HasMany
    {
        return $this->hasMany(CategoriaTraduccion::class);
    }

    /**
     * Products grouped in this category.
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    /**
     * Translation for the given language, falling back to the restaurant's default language.
     */
    public function traduccion(?string $idioma = null): ?CategoriaTraduccion
    {
        return $this->buscarTraduccion($idioma, fn (): ?string => $this->restaurante?->idioma_default);
    }

    /**
     * Scope to visible records.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visible', true);
    }

    /**
     * Scope ordered by the custom position.
     */
    public function scopeOrdenado(Builder $query): Builder
    {
        return $query->orderBy('orden');
    }
}
