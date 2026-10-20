<?php

namespace App\Models;

use App\Models\Scopes\RestauranteScope;
use App\Traits\BelongsToRestaurante;
use App\Traits\ProtegeImagenesDelRestaurante;
use Database\Factories\GaleriaImagenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GaleriaImagen extends Model
{
    /** @use HasFactory<GaleriaImagenFactory> */
    use BelongsToRestaurante, HasFactory, ProtegeImagenesDelRestaurante;

    protected $table = 'galeria_imagenes';

    protected $fillable = [
        'restaurante_id',
        'imagen',
        'descripcion',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    /**
     * Image columns guarded by ProtegeImagenesDelRestaurante.
     *
     * @return array<int, string>
     */
    public function columnasDeImagen(): array
    {
        return ['imagen'];
    }

    public function restauranteIdDeImagenes(): ?int
    {
        $id = $this->restaurante_id ?: RestauranteScope::resolveRestauranteId();

        return $id === null ? null : (int) $id;
    }
}
