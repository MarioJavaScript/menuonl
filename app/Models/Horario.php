<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToRestaurante;
use Database\Factories\HorarioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class Horario extends Model
{
    /** @use HasFactory<HorarioFactory> */
    use Auditable, BelongsToRestaurante, HasFactory;

    protected $table = 'horarios';

    public $timestamps = false;

    protected $fillable = [
        'restaurante_id',
        'dia_semana',
        'abre',
        'cierra',
        'cerrado',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'cerrado' => 'boolean',
        ];
    }

    /**
     * Reject days outside 0 (domingo) .. 6 (sábado) (mirrors the CHECK constraint, which SQLite lacks).
     */
    protected static function booted(): void
    {
        static::saving(function (Horario $horario): void {
            if ($horario->dia_semana < 0 || $horario->dia_semana > 6) {
                throw new InvalidArgumentException('El día de la semana debe estar entre 0 y 6.');
            }
        });
    }

    /**
     * Opening time, stored as H:i:s.
     */
    protected function abre(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => static::normalizarHora($value));
    }

    /**
     * Closing time, stored as H:i:s. A value lower than `abre` means it closes the next day.
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
     * Scope to one day of the week (0 = Sunday ... 6 = Saturday).
     */
    public function scopeDelDia(Builder $query, int $dia): Builder
    {
        return $query->where('dia_semana', $dia);
    }
}
