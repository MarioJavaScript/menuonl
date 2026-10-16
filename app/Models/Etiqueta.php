<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Etiqueta extends Model
{
    use HasFactory;

    protected $table = 'etiquetas';

    protected $fillable = [
        'nombre',
        'slug',
        'codigo',
        'icono',
        'color',
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
        ];
    }

    /**
     * Products assigned to this tag.
     */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_etiqueta', 'etiqueta_id', 'producto_id');
    }

    /**
     * Scope to active tags.
     */
    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Keep 'slug' and 'codigo' in sync.
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?? $this->attributes['codigo'] ?? null,
            set: fn (?string $value) => [
                'slug' => $value,
                'codigo' => $this->attributes['codigo'] ?? $value,
            ]
        );
    }

    /**
     * Keep 'codigo' and 'slug' in sync.
     */
    protected function codigo(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?? $this->attributes['slug'] ?? null,
            set: fn (?string $value) => [
                'codigo' => $value,
                'slug' => $this->attributes['slug'] ?? $value,
            ]
        );
    }
}
