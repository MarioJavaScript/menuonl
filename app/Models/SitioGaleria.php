<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToRestaurante;
use App\Traits\ImagenesDelSitio;
use App\Traits\ProtegeImagenesDelRestaurante;
use Database\Factories\SitioGaleriaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Foto de la galería del sitio. Máximo MAX_POR_RESTAURANTE por restaurante, forzado aquí para que ningún
 * camino (panel, seeder, consola) pueda superarlo.
 */
class SitioGaleria extends Model
{
    /** @use HasFactory<SitioGaleriaFactory> */
    use Auditable, BelongsToRestaurante, HasFactory, ImagenesDelSitio, ProtegeImagenesDelRestaurante;

    public const int MAX_POR_RESTAURANTE = 12;

    public const int MAX_TITULO = 60;

    public const int MAX_TEXTO_ALTERNATIVO = 160;

    public const int MAX_CREDITOS = 150;

    protected $table = 'sitio_galeria';

    protected $fillable = [
        'restaurante_id',
        'imagen',
        'titulo',
        'texto_alternativo',
        'creditos',
        'orden',
        'visible',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'visible' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SitioGaleria $foto): void {
            $existentes = static::withoutGlobalScopes()->where('restaurante_id', $foto->restaurante_id)->count();

            if ($existentes >= self::MAX_POR_RESTAURANTE) {
                throw ValidationException::withMessages(['imagen' => 'La galería admite como máximo '.self::MAX_POR_RESTAURANTE.' imágenes.']);
            }
        });
    }

    /**
     * @param  Builder<SitioGaleria>  $query
     * @return Builder<SitioGaleria>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visible', true);
    }

    /**
     * @param  Builder<SitioGaleria>  $query
     * @return Builder<SitioGaleria>
     */
    public function scopeOrdenado(Builder $query): Builder
    {
        return $query->orderBy('orden')->orderBy('id');
    }

    /**
     * @return array<int, string>
     */
    public function columnasDeImagen(): array
    {
        return ['imagen'];
    }

    public function restauranteIdDeImagenes(): ?int
    {
        return $this->restaurante_id === null ? null : (int) $this->restaurante_id;
    }
}
