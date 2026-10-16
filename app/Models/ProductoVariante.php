<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoVariante extends Model
{
    use Auditable, HasFactory;

    protected $table = 'producto_variantes';

    protected $fillable = [
        'producto_id',
        'precio',
        'disponible',
        'orden',
    ];

    /**
     * Boot model to enforce security constraints.
     */
    protected static function booted(): void
    {
        static::saving(function (ProductoVariante $variante) {
            if ($variante->precio !== null && (float) $variante->precio < 0) {
                throw new \InvalidArgumentException('El precio de la variante no puede ser negativo.');
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'disponible' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * Parent product.
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /**
     * Variant translations.
     */
    public function traducciones(): HasMany
    {
        return $this->hasMany(VarianteTraduccion::class, 'variante_id');
    }

    /**
     * Scope to available variants.
     */
    public function scopeDisponible(Builder $query): Builder
    {
        return $query->where('disponible', true);
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
    public function traduccion(?string $idioma = null): ?VarianteTraduccion
    {
        $idiomaDefault = $this->producto?->restaurante?->idioma_default ?? 'es';
        $targetIdioma = $idioma ?: $idiomaDefault;

        if ($this->relationLoaded('traducciones')) {
            $translation = $this->traducciones->firstWhere('idioma', $targetIdioma);

            if (! $translation && $targetIdioma !== $idiomaDefault) {
                $translation = $this->traducciones->firstWhere('idioma', $idiomaDefault);
            }

            return $translation;
        }

        $translation = $this->traducciones()->where('idioma', $targetIdioma)->first();

        if (! $translation && $targetIdioma !== $idiomaDefault) {
            $translation = $this->traducciones()->where('idioma', $idiomaDefault)->first();
        }

        return $translation;
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
}
