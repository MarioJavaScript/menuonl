<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToRestaurante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Horario extends Model
{
    use Auditable, BelongsToRestaurante, HasFactory;

    protected $table = 'horarios';

    protected $fillable = [
        'restaurante_id',
        'dia_semana',
        'turno',
        'abre',
        'cierra',
        'cerrado',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'turno' => 'integer',
            'cerrado' => 'boolean',
        ];
    }

    /**
     * Scope for a specific day of the week (0 = Sunday ... 6 = Saturday).
     */
    public function scopeDelDia(Builder $query, int $dia): Builder
    {
        return $query->where('dia_semana', $dia);
    }

    /**
     * Check if this shift crosses midnight into the next day.
     */
    public function esNocturno(): bool
    {
        return ! $this->cerrado &&
            $this->abre !== null &&
            $this->cierra !== null &&
            $this->cierra < $this->abre;
    }
}
