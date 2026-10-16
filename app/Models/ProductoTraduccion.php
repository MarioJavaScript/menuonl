<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoTraduccion extends Model
{
    use HasFactory;

    protected $table = 'producto_traducciones';

    protected $fillable = [
        'producto_id',
        'idioma',
        'nombre',
        'descripcion',
    ];

    /**
     * Parent product.
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
