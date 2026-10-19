<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToRestaurante;
use App\Traits\ImagenesDelSitio;
use App\Traits\ProtegeImagenesDelRestaurante;
use Database\Factories\SitioConfiguracionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Contenido editable del sitio del restaurante (/sitio/{slug}). Un solo idioma: el idioma_default del
 * restaurante (las traducciones del sitio quedan fuera por ahora).
 */
class SitioConfiguracion extends Model
{
    /** @use HasFactory<SitioConfiguracionFactory> */
    use Auditable, BelongsToRestaurante, HasFactory, ImagenesDelSitio, ProtegeImagenesDelRestaurante;

    protected $table = 'sitio_configuraciones';

    /**
     * Máximos de longitud: los comparten la validación del panel y las columnas de la tabla.
     */
    public const int MAX_SUBTITULO = 160;

    public const int MAX_ETIQUETA = 30;

    public const int MAX_TITULO = 80;

    public const int MAX_PARRAFO = 600;

    public const int MAX_CREDITOS = 150;

    public const int MAX_ESTADISTICAS = 3;

    public const int MAX_DATO_ESTADISTICA = 30;

    protected $attributes = [
        'reservas_activas' => true,
    ];

    protected $fillable = [
        'restaurante_id',
        'hero_imagen',
        'hero_imagen_creditos',
        'hero_subtitulo',
        'historia_etiqueta',
        'historia_titulo',
        'historia_parrafo_1',
        'historia_parrafo_2',
        'historia_imagen',
        'historia_imagen_creditos',
        'estadisticas',
        'reservas_activas',
    ];

    protected function casts(): array
    {
        return [
            'estadisticas' => 'array',
            'reservas_activas' => 'boolean',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function columnasDeImagen(): array
    {
        return ['hero_imagen', 'historia_imagen'];
    }

    public function restauranteIdDeImagenes(): ?int
    {
        return $this->restaurante_id === null ? null : (int) $this->restaurante_id;
    }
}
