<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared translation lookup for models with a `traducciones()` relationship.
 *
 * Works on the loaded `traducciones` collection, so eager loading it avoids N+1 queries.
 */
trait HasTraducciones
{
    /**
     * Find the translation for the requested language, falling back to the default language.
     */
    protected function buscarTraduccion(?string $idioma, string|\Closure|null $idiomaDefault = null): ?Model
    {
        if ($idioma !== null) {
            $traduccion = $this->traducciones->firstWhere('idioma', $idioma);
            if ($traduccion !== null) {
                return $traduccion;
            }
        }

        $default = $idiomaDefault instanceof \Closure ? $idiomaDefault() : $idiomaDefault;
        $idioma ??= $default;

        if ($idioma === null) {
            return null;
        }

        return $this->traducciones->firstWhere('idioma', $idioma)
            ?? ($default !== null && $default !== $idioma ? $this->traducciones->firstWhere('idioma', $default) : null);
    }
}
