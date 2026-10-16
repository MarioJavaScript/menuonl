<?php

namespace App\Models;

use Database\Factories\EtiquetaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Etiqueta extends Model
{
    /** @use HasFactory<EtiquetaFactory> */
    use HasFactory;

    /**
     * Fixed tag catalog: codigo => icono.
     *
     * @var array<string, string>
     */
    public const CATALOGO = [
        'vegano' => 'leaf',
        'vegetariano' => 'carrot',
        'picante' => 'flame',
        'sin_gluten' => 'wheat-off',
        'sin_lactosa' => 'milk-off',
        'nuevo' => 'sparkles',
        'recomendado' => 'star',
    ];

    protected $table = 'etiquetas';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'icono',
    ];

    /**
     * Products carrying this tag.
     */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_etiqueta');
    }
}
