<?php

namespace App\Models;

use App\Traits\InvalidaElMenuDelRestaurante;
use Database\Factories\CategoriaTraduccionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoriaTraduccion extends Model
{
    /** @use HasFactory<CategoriaTraduccionFactory> */
    use HasFactory, InvalidaElMenuDelRestaurante;

    protected $table = 'categoria_traducciones';

    public $timestamps = false;

    protected $fillable = [
        'categoria_id',
        'idioma',
        'nombre',
        'descripcion',
    ];

    /**
     * Translated category.
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function restauranteIdDelMenu(): ?int
    {
        $restauranteId = Categoria::withoutGlobalScopes()->whereKey($this->categoria_id)->value('restaurante_id');

        return $restauranteId === null ? null : (int) $restauranteId;
    }
}
