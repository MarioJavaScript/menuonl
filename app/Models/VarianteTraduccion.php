<?php

namespace App\Models;

use Database\Factories\VarianteTraduccionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VarianteTraduccion extends Model
{
    /** @use HasFactory<VarianteTraduccionFactory> */
    use HasFactory;

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
}
