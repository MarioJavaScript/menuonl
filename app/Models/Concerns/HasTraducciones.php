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
    protected function buscarTraduccion(?string $idioma, ?string $idiomaDefault): ?Model
    {
        $idioma ??= $idiomaDefault;

        return $this->traducciones->firstWhere('idioma', $idioma)
            ?? $this->traducciones->firstWhere('idioma', $idiomaDefault);
    }
}
