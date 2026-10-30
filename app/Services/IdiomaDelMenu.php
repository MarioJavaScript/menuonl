<?php

namespace App\Services;

use App\Models\Restaurante;

/**
 * Idioma con el que se muestra el menú público (web y API): el de ?lang= si el restaurante lo tiene activo y, si no,
 * su idioma principal. La cabecera Accept-Language no cuenta: la URL sin ?lang= debe dar siempre lo mismo a todos
 * (el borde de Cloudflare la guarda por dirección y no distingue visitantes).
 */
class IdiomaDelMenu
{
    public const string RESPALDO = 'es';

    /**
     * Idiomas activos del restaurante: el principal primero y luego los demás (necesita la relación idiomas cargada).
     *
     * @return list<string>
     */
    public function disponibles(Restaurante $restaurante): array
    {
        return array_values(array_unique(array_filter([
            $restaurante->idioma_default,
            ...$restaurante->idiomas->pluck('idioma')->all(),
        ])));
    }

    public function resolver(Restaurante $restaurante, mixed $lang): string
    {
        if (is_string($lang) && in_array($lang, $this->disponibles($restaurante), true)) {
            return $lang;
        }

        return $restaurante->idioma_default ?: self::RESPALDO;
    }
}
