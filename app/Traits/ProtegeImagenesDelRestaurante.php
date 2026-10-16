<?php

namespace App\Traits;

use App\Services\WebpImageService;
use Illuminate\Validation\ValidationException;

/**
 * Last line of defence for image columns: whatever reaches the model (a Livewire payload, a seeder,
 * a console command), a stored path must live inside restaurantes/{id}/ of the owning restaurant,
 * and a replaced image is deleted only from that same directory.
 *
 * The using model implements columnasDeImagen() and restauranteIdDeImagenes().
 */
trait ProtegeImagenesDelRestaurante
{
    /**
     * Image columns guarded on this model.
     *
     * @return array<int, string>
     */
    abstract public function columnasDeImagen(): array;

    /**
     * Restaurant that owns the images of this record (null while unsaved, which forbids any image).
     */
    abstract public function restauranteIdDeImagenes(): ?int;

    public static function bootProtegeImagenesDelRestaurante(): void
    {
        static::saving(function (self $modelo): void {
            $servicio = app(WebpImageService::class);

            foreach ($modelo->columnasDeImagen() as $columna) {
                if (! $modelo->isDirty($columna)) {
                    continue;
                }

                $ruta = $modelo->getAttribute($columna);

                if ($ruta === null || $ruta === '') {
                    $modelo->setAttribute($columna, null);

                    continue;
                }

                $restauranteId = $modelo->restauranteIdDeImagenes();

                if ($restauranteId === null) {
                    throw ValidationException::withMessages([$columna => 'La imagen solo puede asignarse a un restaurante ya creado.']);
                }

                $servicio->asegurarRutaDelRestaurante((string) $ruta, $restauranteId, $columna);
            }
        });

        static::saved(function (self $modelo): void {
            $restauranteId = $modelo->restauranteIdDeImagenes();

            if ($restauranteId === null) {
                return;
            }

            foreach ($modelo->columnasDeImagen() as $columna) {
                $anterior = $modelo->getOriginal($columna);

                if ($modelo->isDirty($columna) && is_string($anterior) && $anterior !== '') {
                    app(WebpImageService::class)->deleteFromRestaurante($anterior, $restauranteId);
                }
            }
        });
    }
}
