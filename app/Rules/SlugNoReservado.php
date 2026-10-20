<?php

namespace App\Rules;

use App\Services\Catalogos;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates restaurantes.slug: valid format and not a reserved word that /{slug} would clash with.
 */
class SlugNoReservado implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/\A'.Catalogos::PATRON_SLUG.'\z/', $value) !== 1) {
            $fail('La dirección corta solo puede tener letras minúsculas, números y guiones medios.');

            return;
        }

        if (Catalogos::esSlugReservado($value)) {
            $fail('Esa dirección corta está reservada por el sistema. Elige otra.');
        }
    }
}
