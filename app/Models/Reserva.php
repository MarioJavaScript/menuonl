<?php

namespace App\Models;

use App\Traits\BelongsToRestaurante;
use Database\Factories\ReservaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    /** @use HasFactory<ReservaFactory> */
    use BelongsToRestaurante, HasFactory;

    public const array ESTADOS = ['pendiente', 'confirmada', 'llego', 'cancelada'];

    protected $table = 'reservas';

    protected $fillable = [
        'restaurante_id',
        'nombre',
        'personas',
        'fecha',
        'hora',
        'estado',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'personas' => 'integer',
            'fecha' => 'date:Y-m-d',
        ];
    }
}
