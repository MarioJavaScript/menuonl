<?php

namespace App\Models;

use App\Traits\InvalidaElMenuDelRestaurante;
use Database\Factories\ProductoTraduccionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoTraduccion extends Model
{
    /** @use HasFactory<ProductoTraduccionFactory> */
    use HasFactory, InvalidaElMenuDelRestaurante;

    protected $table = 'producto_traducciones';

    public $timestamps = false;

    protected $fillable = [
        'producto_id',
        'idioma',
        'nombre',
        'descripcion',
    ];

    /**
     * Translated product.
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function restauranteIdDelMenu(): ?int
    {
        $restauranteId = Producto::withoutGlobalScopes()->whereKey($this->producto_id)->value('restaurante_id');

        return $restauranteId === null ? null : (int) $restauranteId;
    }
}
