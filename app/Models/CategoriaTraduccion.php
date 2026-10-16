<?php

namespace App\Models;

use Database\Factories\CategoriaTraduccionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoriaTraduccion extends Model
{
    /** @use HasFactory<CategoriaTraduccionFactory> */
    use HasFactory;

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
}
