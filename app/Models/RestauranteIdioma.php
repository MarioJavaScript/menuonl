<?php

namespace App\Models;

use App\Traits\BelongsToRestaurante;
use App\Traits\InvalidaElMenuDelRestaurante;
use Database\Factories\RestauranteIdiomaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestauranteIdioma extends Model
{
    /** @use HasFactory<RestauranteIdiomaFactory> */
    use BelongsToRestaurante, HasFactory, InvalidaElMenuDelRestaurante;

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

    public function restauranteIdDelMenu(): ?int
    {
        return $this->restaurante_id === null ? null : (int) $this->restaurante_id;
    }
}
