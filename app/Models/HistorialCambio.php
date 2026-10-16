<?php

namespace App\Models;

use App\Traits\BelongsToRestaurante;
use Database\Factories\HistorialCambioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialCambio extends Model
{
    /** @use HasFactory<HistorialCambioFactory> */
    use BelongsToRestaurante, HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'historial_cambios';

    protected $fillable = [
        'restaurante_id',
        'usuario_id',
        'entidad',
        'entidad_id',
        'accion',
        'cambios',
    ];

    protected function casts(): array
    {
        return [
            'entidad_id' => 'integer',
            'cambios' => 'array',
        ];
    }

    /**
     * User who made the change; null when the system did it.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
