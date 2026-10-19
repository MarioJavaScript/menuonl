<?php

namespace App\Traits;

use Illuminate\Validation\ValidationException;

/**
 * Las imágenes del sitio viven solo en restaurantes/{id}/sitio/ del propio restaurante. Se suma a
 * ProtegeImagenesDelRestaurante (que ya rechaza otros restaurantes y el path traversal) y exige además
 * la carpeta `sitio`, de modo que un logo o una foto de producto no puedan reutilizarse aquí.
 *
 * El modelo implementa columnasDeImagen() y restauranteIdDeImagenes() (los pide ProtegeImagenesDelRestaurante).
 */
trait ImagenesDelSitio
{
    public static function bootImagenesDelSitio(): void
    {
        static::saving(function (self $modelo): void {
            foreach ($modelo->columnasDeImagen() as $columna) {
                $ruta = $modelo->getAttribute($columna);

                if (! $modelo->isDirty($columna) || $ruta === null || $ruta === '') {
                    continue;
                }

                $prefijo = 'restaurantes/'.$modelo->restauranteIdDeImagenes().'/sitio/';

                if (! is_string($ruta) || ! str_starts_with($ruta, $prefijo)) {
                    throw ValidationException::withMessages([$columna => 'La imagen del sitio debe estar en la carpeta del sitio de este restaurante.']);
                }
            }
        });
    }
}
