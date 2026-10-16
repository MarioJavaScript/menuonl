<?php

namespace App\Models;

use App\Traits\BelongsToRestaurante;
use Database\Factories\RestauranteIdiomaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestauranteIdioma extends Model
{
    /** @use HasFactory<RestauranteIdiomaFactory> */
    use BelongsToRestaurante, HasFactory;

    protected $table = 'restaurante_idiomas';

    public $timestamps = false;

    protected $fillable = [
        'restaurante_id',
        'idioma',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }
}
