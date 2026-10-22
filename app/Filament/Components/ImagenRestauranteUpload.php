<?php

namespace App\Filament\Components;

use App\Models\Restaurante;
use App\Services\WebpImageService;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Image upload (logo, cover, product photo) that never stores the original.
 *
 * The temporary Livewire upload goes through WebpImageService::procesar(), the same entry point as the
 * /panel uploads (real type, size and pixel caps, EXIF orientation, same crop), and only the generated WebP
 * is written, under restaurantes/{id}/. String values coming back from the form are client-controlled:
 * preventFilePathTampering() only accepts the path the record already has, and the model
 * (ProtegeImagenesDelRestaurante) rejects any other.
 */
class ImagenRestauranteUpload
{
    /**
     * @param  string  $perfil  One of the keys of WebpImageService::PERFILES (logotipo, portada, platillo, galeria)
     */
    public static function make(string $name, string $perfil): FileUpload
    {
        $imagenes = app(WebpImageService::class);

        return FileUpload::make($name)
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize((int) ceil((int) config('menuonl.imagenes.max_bytes') / 1024))
            ->validationMessages([
                'max' => $imagenes->mensajeDemasiadoGrande(),
                'image' => $imagenes->mensajeIlegible(),
                'mimetypes' => $imagenes->mensajeIlegible(),
                'uploaded' => $imagenes->mensajeDemasiadoGrande(),
            ])
            ->helperText($imagenes->ayudaDeSubida($perfil))
            ->disk('public')
            ->visibility('public')
            ->preventFilePathTampering()
            ->saveUploadedFileUsing(function (FileUpload $component, TemporaryUploadedFile $file, ?Model $record) use ($perfil): string {
                $restauranteId = self::restauranteActivoId($record);

                if ($restauranteId === null) {
                    throw ValidationException::withMessages([$component->getStatePath() => 'No hay un restaurante activo.']);
                }

                try {
                    return app(WebpImageService::class)->procesar($file, $restauranteId, $perfil);
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages([
                        $component->getStatePath() => collect($e->errors())->flatten()->first(),
                    ]);
                }
            });
    }

    /**
     * Restaurant whose directory receives the image: the one being edited, otherwise the active tenant.
     */
    private static function restauranteActivoId(?Model $record): ?int
    {
        if ($record instanceof Restaurante && $record->exists) {
            return (int) $record->getKey();
        }

        $id = Filament::getTenant()?->getKey();

        return $id === null ? null : (int) $id;
    }
}
