<?php

namespace App\Models;

use App\Models\Concerns\HasTraducciones;
use App\Traits\BelongsToRestaurante;
use Database\Factories\ProductoFactory;
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
    use BelongsToRestaurante, HasFactory, HasTraducciones, SoftDeletes;

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
        return $this->hasMany(ProductoVariante::class);
    }

    /**
     * Dietary tags (vegano, picante, sin_gluten, ...).
     */
    public function etiquetas(): BelongsToMany
    {
        return $this->belongsToMany(Etiqueta::class, 'producto_etiqueta');
    }

    /**
     * Translation for the given language, falling back to the restaurant's default language.
     */
    public function traduccion(?string $idioma = null): ?ProductoTraduccion
    {
        return $this->buscarTraduccion($idioma, $this->restaurante?->idioma_default);
    }
}
