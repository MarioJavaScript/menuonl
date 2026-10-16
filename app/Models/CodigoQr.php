<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToRestaurante;
use Database\Factories\CodigoQrFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodigoQr extends Model
{
    /** @use HasFactory<CodigoQrFactory> */
    use Auditable, BelongsToRestaurante, HasFactory;

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

    /**
     * Scope to active QR codes.
     */
    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Whether the code belongs to a specific table.
     */
    public function esMesa(): bool
    {
        return filled($this->mesa);
    }

    /**
     * Check if the QR code is active.
     */
    public function isActivo(): bool
    {
        return (bool) $this->activo;
    }
}
