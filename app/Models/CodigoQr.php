<?php

namespace App\Models;

use App\Traits\BelongsToRestaurante;
use Database\Factories\CodigoQrFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodigoQr extends Model
{
    /** @use HasFactory<CodigoQrFactory> */
    use BelongsToRestaurante, HasFactory;

    protected $table = 'codigos_qr';

    protected $fillable = [
        'restaurante_id',
        'nombre',
        'mesa',
        'codigo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
