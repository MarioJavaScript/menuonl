<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToRestaurante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use Auditable, BelongsToRestaurante, HasFactory, SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'restaurante_id',
        'categoria_id',
        'precio',
        'precio_oferta',
        'foto_url',
        'imagen',
        'disponible',
        'visible',
        'destacado',
        'orden',
    ];

    /**
     * Boot model to enforce security constraints.
     */
    protected static function booted(): void
    {
        static::saving(function (Producto $producto) {
            if ($producto->precio !== null && (float) $producto->precio < 0) {
                throw new \InvalidArgumentException('El precio del producto no puede ser negativo.');
            }

            if ($producto->precio_oferta !== null && (float) $producto->precio_oferta < 0) {
                throw new \InvalidArgumentException('El precio de oferta no puede ser negativo.');
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
            'precio_oferta' => 'decimal:2',
            'disponible' => 'boolean',
            'visible' => 'boolean',
            'destacado' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * Parent category.
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    /**
     * Product translations.
     */
    public function traducciones(): HasMany
    {
        return $this->hasMany(ProductoTraduccion::class, 'producto_id');
    }

    /**
     * Product variants (sizes, portions).
     */
    public function variantes(): HasMany
    {
        return $this->hasMany(ProductoVariante::class, 'producto_id');
    }

    /**
     * Dietary and promotional tags.
     */
    public function etiquetas(): BelongsToMany
    {
        return $this->belongsToMany(Etiqueta::class, 'producto_etiqueta', 'producto_id', 'etiqueta_id');
    }

    /**
     * Scope to visible products.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visible', true);
    }

    /**
     * Scope to available products.
     */
    public function scopeDisponible(Builder $query): Builder
    {
        return $query->where('disponible', true);
    }

    /**
     * Scope to featured/recommended products.
     */
    public function scopeDestacado(Builder $query): Builder
    {
        return $query->where('destacado', true);
    }

    /**
     * Scope ordered by custom position.
     */
    public function scopeOrdenado(Builder $query): Builder
    {
        return $query->orderBy('orden');
    }

    /**
     * Keep 'foto_url' and 'imagen' synced.
     */
    protected function fotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?? $this->attributes['imagen'] ?? null,
            set: fn (?string $value) => [
                'foto_url' => $value,
                'imagen' => $this->attributes['imagen'] ?? $value,
            ]
        );
    }

    /**
     * Keep 'imagen' and 'foto_url' synced.
     */
    protected function imagen(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?? $this->attributes['foto_url'] ?? null,
            set: fn (?string $value) => [
                'imagen' => $value,
                'foto_url' => $this->attributes['foto_url'] ?? $value,
            ]
        );
    }

    /**
     * Retrieve translation with fallback to restaurant's default language.
     */
    public function traduccion(?string $idioma = null): ?ProductoTraduccion
    {
        $idiomaDefault = $this->restaurante?->idioma_default ?? 'es';
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

    /**
     * Returns effective active price (offer price if present and lower, else normal price).
     */
    public function precioVigente(): string
    {
        if ($this->precio_oferta !== null && (float) $this->precio_oferta < (float) $this->precio) {
            return (string) $this->precio_oferta;
        }

        return (string) $this->precio;
    }

    /**
     * Check if product has any variants configured.
     */
    public function tieneVariantes(): bool
    {
        if ($this->relationLoaded('variantes')) {
            return $this->variantes->isNotEmpty();
        }

        return $this->variantes()->exists();
    }
}
