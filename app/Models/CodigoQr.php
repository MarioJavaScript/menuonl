<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToRestaurante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodigoQr extends Model
{
    use Auditable, BelongsToRestaurante, HasFactory;

    protected $table = 'codigos_qr';

    protected $fillable = [
        'restaurante_id',
        'nombre',
        'codigo',
        'tipo',
        'mesa',
        'activo',
        'contador_escaneos',
        'ultimo_escaneo_en',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'contador_escaneos' => 'integer',
            'ultimo_escaneo_en' => 'datetime',
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
     * Scope to table-specific QR codes.
     */
    public function scopeDeMesa(Builder $query): Builder
    {
        return $query->where('tipo', 'mesa');
    }

    /**
     * Scope to general QR codes.
     */
    public function scopeGeneral(Builder $query): Builder
    {
        return $query->where('tipo', 'general');
    }

    /**
     * Check if this QR code is for a specific table.
     */
    public function esMesa(): bool
    {
        return $this->tipo === 'mesa' || ! empty($this->mesa);
    }

    /**
     * Accessor for 'nombre' with automatic fallback.
     */
    protected function nombre(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($value !== null && $value !== '') {
                    return $value;
                }

                if ($this->tipo === 'mesa' && ! empty($this->mesa)) {
                    return "Mesa {$this->mesa}";
                }

                return 'General';
            }
        );
    }
}
