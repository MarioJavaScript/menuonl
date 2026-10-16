<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToRestaurante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Categoria extends Model
{
    use Auditable, BelongsToRestaurante, HasFactory, SoftDeletes;

    protected $table = 'categorias';

    protected $fillable = [
        'restaurante_id',
        'orden',
        'visible',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * Category translations.
     */
    public function traducciones(): HasMany
    {
        return $this->hasMany(CategoriaTraduccion::class, 'categoria_id');
    }

    /**
     * Products belonging to this category.
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }

    /**
     * Scope to visible categories.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visible', true);
    }

    /**
     * Scope ordered by custom position.
     */
    public function scopeOrdenado(Builder $query): Builder
    {
        return $query->orderBy('orden');
    }

    /**
     * Retrieve translation with fallback to restaurant's default language.
     */
    public function traduccion(?string $idioma = null): ?CategoriaTraduccion
    {
        $idiomaDefault = $this->restaurante?->idioma_default ?? 'es';
        $targetIdioma = $idioma ?: $idiomaDefault;

        // If translations relationship is already loaded, use collection methods to avoid N+1 queries
        if ($this->relationLoaded('traducciones')) {
            $translation = $this->traducciones->firstWhere('idioma', $targetIdioma);

            if (! $translation && $targetIdioma !== $idiomaDefault) {
                $translation = $this->traducciones->firstWhere('idioma', $idiomaDefault);
            }

            return $translation ?? $this->traducciones->first();
        }

        // Query database
        $translation = $this->traducciones()->where('idioma', $targetIdioma)->first();

        if (! $translation && $targetIdioma !== $idiomaDefault) {
            $translation = $this->traducciones()->where('idioma', $idiomaDefault)->first();
        }

        return $translation ?? $this->traducciones()->first();
    }

    /**
     * Helper to get translated name with fallback.
     */
    public function nombre(?string $idioma = null): ?string
    {
        return $this->traduccion($idioma)?->nombre;
    }

    /**
     * Alias for nombre().
     */
    public function getNombre(?string $idioma = null): ?string
    {
        return $this->nombre($idioma);
    }

    /**
     * Helper to get translated description with fallback.
     */
    public function descripcion(?string $idioma = null): ?string
    {
        return $this->traduccion($idioma)?->descripcion;
    }

    /**
     * Alias for descripcion().
     */
    public function getDescripcion(?string $idioma = null): ?string
    {
        return $this->descripcion($idioma);
    }
}
