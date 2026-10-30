<?php

namespace App\Models;

use App\Models\Concerns\HasTraducciones;
use App\Models\Scopes\RestauranteScope;
use App\Traits\Auditable;
use App\Traits\BelongsToRestaurante;
use App\Traits\ProtegeImagenesDelRestaurante;
use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use Auditable, BelongsToRestaurante, HasFactory, HasTraducciones, ProtegeImagenesDelRestaurante, SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'restaurante_id',
        'categoria_id',
        'precio',
        'precio_oferta',
        'imagen',
        'disponible',
        'visible',
        'destacado',
        'orden',
    ];

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
     * Image columns guarded by ProtegeImagenesDelRestaurante.
     *
     * @return array<int, string>
     */
    public function columnasDeImagen(): array
    {
        return ['imagen'];
    }

    /**
     * Owner of the images; while creating, the active restaurant (BelongsToRestaurante assigns it later).
     */
    public function restauranteIdDeImagenes(): ?int
    {
        $id = $this->restaurante_id ?: RestauranteScope::resolveRestauranteId();

        return $id === null ? null : (int) $id;
    }

    /**
     * Reject negative prices (mirrors the CHECK constraints, which SQLite lacks).
     */
    protected static function booted(): void
    {
        static::saving(function (Producto $producto): void {
            if ((float) $producto->precio < 0) {
                throw new InvalidArgumentException('El precio no puede ser negativo.');
            }

            if ($producto->precio_oferta !== null && (float) $producto->precio_oferta < 0) {
                throw new InvalidArgumentException('El precio de oferta no puede ser negativo.');
            }
        });
    }

    /**
     * Category this product belongs to.
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Translations of the product name and description.
     */
    public function traducciones(): HasMany
    {
        return $this->hasMany(ProductoTraduccion::class);
    }

    /**
     * Sizes or presentations, each with its own price.
     */
    public function variantes(): HasMany
    {
        return $this->hasMany(ProductoVariante::class)->chaperone();
    }

    /**
     * Dietary tags (vegano, picante, sin_gluten, ...).
     */
    public function etiquetas(): BelongsToMany
    {
        return $this->belongsToMany(Etiqueta::class, 'producto_etiqueta')->using(ProductoEtiqueta::class);
    }

    /**
     * Translation for the given language, falling back to the restaurant's default language.
     */
    public function traduccion(?string $idioma = null): ?ProductoTraduccion
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

    /**
     * Scope to products that are not sold out.
     */
    public function scopeDisponible(Builder $query): Builder
    {
        return $query->where('disponible', true);
    }

    /**
     * Scope to featured (recommended) products.
     */
    public function scopeDestacado(Builder $query): Builder
    {
        return $query->where('destacado', true);
    }

    /**
     * Price a customer pays: the offer price when it is lower than the regular one.
     */
    public function precioVigente(): string
    {
        if ($this->precio_oferta !== null && (float) $this->precio_oferta < (float) $this->precio) {
            return (string) $this->precio_oferta;
        }

        return (string) $this->precio;
    }

    /**
     * Whether the product has variants (the menu then shows each variant's price).
     */
    public function tieneVariantes(): bool
    {
        if ($this->relationLoaded('variantes')) {
            return $this->variantes->isNotEmpty();
        }

        return $this->variantes()->exists();
    }
}
