<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoriaTraduccion extends Model
{
    use HasFactory;

    protected $table = 'categoria_traducciones';

    protected $fillable = [
        'categoria_id',
        'idioma',
        'nombre',
        'descripcion',
    ];

    /**
     * Parent category.
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }
}
