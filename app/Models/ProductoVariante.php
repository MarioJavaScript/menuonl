<?php

namespace App\Models;

use App\Models\Concerns\HasTraducciones;
use App\Traits\Auditable;
use Database\Factories\ProductoVarianteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class ProductoVariante extends Model
{
    /** @use HasFactory<ProductoVarianteFactory> */
    use Auditable, HasFactory, HasTraducciones;

    protected $table = 'producto_variantes';

    public $timestamps = false;

    protected $fillable = [
        'producto_id',
        'precio',
        'disponible',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'disponible' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * Reject negative prices (mirrors the CHECK constraint, which SQLite lacks).
     */
    protected static function booted(): void
    {
        static::saving(function (ProductoVariante $variante): void {
            if ((float) $variante->precio < 0) {
                throw new InvalidArgumentException('El precio de la variante no puede ser negativo.');
            }
        });
    }

    /**
     * Product this variant belongs to.
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Translations of the variant name.
     */
    public function traducciones(): HasMany
    {
        return $this->hasMany(VarianteTraduccion::class, 'variante_id');
    }

    /**
     * Translation for the given language, falling back to the restaurant's default language.
     */
    public function traduccion(?string $idioma = null): ?VarianteTraduccion
    {
        return $this->buscarTraduccion($idioma, fn (): ?string => $this->producto?->restaurante?->idioma_default);
    }

    /**
     * Scope to variants that are not sold out.
     */
    public function scopeDisponible(Builder $query): Builder
    {
        return $query->where('disponible', true);
    }

    /**
     * Scope ordered by the custom position.
     */
    public function scopeOrdenado(Builder $query): Builder
    {
        return $query->orderBy('orden');
    }
}
