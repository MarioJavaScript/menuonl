<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates codigos_qr.codigo: 1 to 16 letters, digits, underscores or hyphens (docs/02-seguridad.md §1).
 */
class CodigoQrValido implements ValidationRule
{
    public const string PATRON = '/\A[A-Za-z0-9_-]{1,16}\z/';

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATRON, $value) !== 1) {
            $fail('El código solo puede tener letras, números, guion y guion bajo (máximo 16 caracteres).');
        }
    }
}
