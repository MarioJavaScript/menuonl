<?php

namespace App\Models;

use App\Traits\InvalidaElMenuDelRestaurante;
use Database\Factories\VarianteTraduccionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VarianteTraduccion extends Model
{
    /** @use HasFactory<VarianteTraduccionFactory> */
    use HasFactory, InvalidaElMenuDelRestaurante;

    protected $table = 'variante_traducciones';

    public $timestamps = false;

    protected $fillable = [
        'variante_id',
        'idioma',
        'nombre',
    ];

    /**
     * Translated variant.
     */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function restauranteIdDelMenu(): ?int
    {
        $restauranteId = Producto::withoutGlobalScopes()
            ->whereIn('id', ProductoVariante::query()->select('producto_id')->whereKey($this->variante_id))
            ->value('restaurante_id');

        return $restauranteId === null ? null : (int) $restauranteId;
    }
}
