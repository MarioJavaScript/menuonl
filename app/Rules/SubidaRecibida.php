<?php

namespace App\Rules;

use App\Services\WebpImageService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Regla de la subida temporal de Livewire/Filament (sustituye a `required|file|max:12288`).
 *
 * Laravel marca cualquier subida inválida con el mensaje genérico "uploaded" si el campo lleva una regla de archivo
 * (`file`, `max`...), sin distinguir el motivo. Por eso esta regla hace sola esas comprobaciones: dice en español si fue
 * el límite del servidor, una subida incompleta o el archivo temporal, y si el archivo pesa demasiado. El código de PHP
 * se registra; la persona nunca ve rutas ni detalles internos.
 */
class SubidaRecibida implements ValidationRule
{
    public function __construct(private int $maximoKb = 12288) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $imagenes = app(WebpImageService::class);

        if (! $value instanceof UploadedFile) {
            $fail($imagenes->mensajeSubidaIncompleta());

            return;
        }

        if (! $value->isValid()) {
            Log::error('Subida temporal de imagen fallida.', ['codigo_php' => $value->getError()]);

            $fail($imagenes->mensajeParaErrorDeSubida($value->getError()));

            return;
        }

        if ($value->getSize() > $this->maximoKb * 1024) {
            $fail($imagenes->mensajeDemasiadoGrande());
        }
    }
}
