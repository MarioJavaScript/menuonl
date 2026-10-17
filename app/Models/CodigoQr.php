<?php

namespace App\Models;

use App\Enums\TipoCodigoAcceso;
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
        'tipo',
        'codigo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'tipo' => TipoCodigoAcceso::class,
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
     * Whether the code is written on an NFC tag instead of printed as a QR.
     */
    public function esNfc(): bool
    {
        return $this->tipo === TipoCodigoAcceso::Nfc;
    }

    /**
     * Full short URL of the code, built from app.url so it never depends on the request Host.
     */
    public function urlCorta(): string
    {
        return rtrim((string) config('app.url'), '/').route('qr.redirect', ['codigo' => $this->codigo], false);
    }

    /**
     * Check if the QR code is active.
     */
    public function isActivo(): bool
    {
        return (bool) $this->activo;
    }
}
