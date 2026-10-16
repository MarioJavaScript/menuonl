<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToRestaurante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

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
     * Reject days outside 0 (domingo) .. 6 (sábado).
     */
    protected static function booted(): void
    {
        static::saving(function (Horario $horario): void {
            if ($horario->dia_semana === null || $horario->dia_semana < 0 || $horario->dia_semana > 6) {
                throw new InvalidArgumentException('El día de la semana debe estar entre 0 y 6.');
            }
        });
    }

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
     * Opening time, stored as H:i:s.
     */
    protected function abre(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => static::normalizarHora($value));
    }

    /**
     * Closing time, stored as H:i:s.
     */
    protected function cierra(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => static::normalizarHora($value));
    }

    /**
     * Normalize "13:00" and "13:00:00" to the same H:i:s string so times compare correctly.
     */
    protected static function normalizarHora(?string $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->format('H:i:s');
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
