<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToRestaurante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contacto extends Model
{
    use Auditable, BelongsToRestaurante, HasFactory;

    protected $table = 'contactos';

    protected $fillable = [
        'restaurante_id',
        'tipo',
        'etiqueta',
        'valor',
        'orden',
        'visible',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * Scope to visible contacts.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visible', true);
    }

    /**
     * Scope ordered by custom position.
     */
    public function scopeOrdenado(Builder $query): Builder
    {
        return $query->orderBy('orden');
    }

    /**
     * Validate whether a contact URL/value is permitted according to security guidelines.
     * Only allows safe schemes: http, https, tel, and mailto (strictly rejects javascript:, data:, vbscript:).
     */
    public static function esUrlPermitida(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $trimmed = trim($url);

        // Reject dangerous schemes
        if (preg_match('/^\s*(javascript|data|vbscript):/i', $trimmed)) {
            return false;
        }

        // If a scheme is specified (has a protocol prefix), only allow safe schemes
        if (preg_match('/^\s*([a-zA-Z0-9+.-]+):/', $trimmed, $matches)) {
            $scheme = strtolower($matches[1]);

            return in_array($scheme, ['http', 'https', 'tel', 'mailto'], true);
        }

        return true;
    }
}
