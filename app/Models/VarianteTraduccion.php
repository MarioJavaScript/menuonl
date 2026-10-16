<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VarianteTraduccion extends Model
{
    use HasFactory;

    protected $table = 'variante_traducciones';

    protected $fillable = [
        'variante_id',
        'idioma',
        'nombre',
    ];

    /**
     * Parent product variant.
     */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
}
