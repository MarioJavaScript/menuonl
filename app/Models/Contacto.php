<?php

namespace App\Models;

use App\Traits\BelongsToRestaurante;
use Database\Factories\ContactoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contacto extends Model
{
    /** @use HasFactory<ContactoFactory> */
    use BelongsToRestaurante, HasFactory;

    protected $table = 'contactos';

    protected $fillable = [
        'restaurante_id',
        'tipo',
        'etiqueta',
        'valor',
        'orden',
        'visible',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'visible' => 'boolean',
        ];
    }
}
