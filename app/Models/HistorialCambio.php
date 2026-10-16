<?php

namespace App\Models;

use App\Traits\BelongsToRestaurante;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialCambio extends Model
{
    use BelongsToRestaurante, HasFactory;

    protected $table = 'historial_cambios';

    public const UPDATED_AT = null;

    protected $fillable = [
        'restaurante_id',
        'usuario_id',
        'entidad',
        'entidad_id',
        'accion',
        'cambios',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cambios' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * User who made the change (null if performed by system).
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Alias for usuario.
     */
    public function user(): BelongsTo
    {
        return $this->usuario();
    }
}
