<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates contactos.valor: only the http, https, tel and mailto schemes may ever be stored
 * (docs/02-seguridad.md §4), so a `javascript:` or `data:` value can never reach a public link.
 */
class ValorContactoSeguro implements ValidationRule
{
    /**
     * @var list<string>
     */
    public const array ESQUEMAS_PERMITIDOS = ['http', 'https', 'tel', 'mailto'];

    public function __construct(private readonly ?string $tipo = null) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('El valor del contacto es obligatorio.');

            return;
        }

        // Control characters hide schemes ("java\tscript:") from naive checks.
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            $fail('El valor del contacto contiene caracteres no permitidos.');

            return;
        }

        $valor = trim($value);
        $esquema = $this->esquemaDe($valor);

        if ($esquema !== null && ! in_array($esquema, self::ESQUEMAS_PERMITIDOS, true)) {
            $fail('Solo se permiten enlaces http, https, tel o mailto.');

            return;
        }

        $mensaje = match ($this->tipo) {
            'telefono', 'whatsapp' => $this->validarTelefono($valor, $esquema),
            'email' => $this->validarCorreo($valor, $esquema),
            'instagram', 'facebook', 'tiktok', 'web' => $this->validarUrlWeb($valor, $esquema),
            default => $this->validarGenerico($valor, $esquema),
        };

        if ($mensaje !== null) {
            $fail($mensaje);
        }
    }

    /**
     * Scheme of a value like "scheme:rest", lower-cased, or null when it has none.
     */
    private function esquemaDe(string $valor): ?string
    {
        return preg_match('/\A([A-Za-z][A-Za-z0-9+.\-]*):/', $valor, $coincidencia) === 1
            ? strtolower($coincidencia[1])
            : null;
    }

    private function validarTelefono(string $valor, ?string $esquema): ?string
    {
        $numero = $esquema === 'tel' ? substr($valor, 4) : $valor;

        if ($esquema !== null && $esquema !== 'tel') {
            return 'El teléfono debe ser un número o un enlace tel:.';
        }

        return preg_match('/\A\+?[0-9 ()\-]{5,30}\z/', $numero) === 1
            ? null
            : 'El teléfono solo puede tener dígitos, espacios, paréntesis y guiones.';
    }

    private function validarCorreo(string $valor, ?string $esquema): ?string
    {
        $correo = $esquema === 'mailto' ? substr($valor, 7) : $valor;

        if ($esquema !== null && $esquema !== 'mailto') {
            return 'El correo debe ser una dirección o un enlace mailto:.';
        }

        return filter_var($correo, FILTER_VALIDATE_EMAIL) !== false ? null : 'El correo no es válido.';
    }

    private function validarUrlWeb(string $valor, ?string $esquema): ?string
    {
        if (! in_array($esquema, ['http', 'https'], true) || filter_var($valor, FILTER_VALIDATE_URL) === false) {
            return 'Escribe una URL completa que empiece con http:// o https://.';
        }

        return parse_url($valor, PHP_URL_HOST) ? null : 'La URL no tiene un dominio válido.';
    }

    private function validarGenerico(string $valor, ?string $esquema): ?string
    {
        return match ($esquema) {
            'http', 'https' => $this->validarUrlWeb($valor, $esquema),
            'mailto' => $this->validarCorreo($valor, $esquema),
            'tel' => $this->validarTelefono($valor, $esquema),
            default => null,
        };
    }
}
