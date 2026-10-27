<?php

namespace App\Models;

use App\Traits\BelongsToRestaurante;
use Database\Factories\ReservaEventoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of the reservation history: who did what and when. Holds no guest data, only state, time and date changes.
 */
class ReservaEvento extends Model
{
    /** @use HasFactory<ReservaEventoFactory> */
    use BelongsToRestaurante, HasFactory;

    public const UPDATED_AT = null;

    public const array ACCIONES = ['creada', 'confirmada', 'llego', 'cancelada', 'restaurada', 'movida', 'eliminada'];

    public const array ORIGENES = ['panel', 'api', 'admin'];

    protected $table = 'reserva_eventos';

    protected $fillable = [
        'restaurante_id',
        'reserva_id',
        'usuario_id',
        'accion',
        'origen',
        'estado_antes',
        'estado_despues',
        'hora_antes',
        'hora_despues',
        'fecha_antes',
        'fecha_despues',
    ];

    protected function casts(): array
    {
        return [
            'reserva_id' => 'integer',
            'fecha_antes' => 'date:Y-m-d',
            'fecha_despues' => 'date:Y-m-d',
        ];
    }

    /**
     * User who made the change; null when the user was deleted.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
