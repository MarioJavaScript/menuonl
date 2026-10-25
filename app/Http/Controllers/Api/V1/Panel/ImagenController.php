<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Models\GaleriaImagen;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Services\WebpImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Photo uploads from the panel: dish photos (module "menu"), hero and gallery (module "ajustes").
 *
 * Every upload goes through WebpImageService::procesar(), the same entry point as the Filament panel:
 * real type, size and pixel caps, EXIF orientation, fixed crop, WebP with responsive copies, always inside
 * the restaurant's own directory.
 */
class ImagenController extends Controller
{
    public function __construct(private WebpImageService $imagenes) {}

    public function producto(Request $request, Restaurante $restaurante, Producto $producto): JsonResponse
    {
        Gate::authorize('update', $producto);
        $archivo = $this->archivo($request);

        $producto->update(['imagen' => $this->imagenes->procesar($archivo, $restaurante->id, 'platillo')]);

        return response()->json(['data' => ['imagen' => $this->imagenes->url($producto->imagen, 480)]]);
    }

    public function quitarProducto(Restaurante $restaurante, Producto $producto): JsonResponse
    {
        Gate::authorize('update', $producto);

        $producto->update(['imagen' => null]);

        return response()->json(['data' => ['imagen' => null]]);
    }

    public function hero(Request $request, Restaurante $restaurante): JsonResponse
    {
        $archivo = $this->archivo($request);

        $restaurante->update(['portada' => $this->imagenes->procesar($archivo, $restaurante->id, 'portada')]);

        return response()->json(['data' => ['hero' => $this->imagenes->url($restaurante->portada, 960)]]);
    }

    public function quitarHero(Restaurante $restaurante): JsonResponse
    {
        $restaurante->update(['portada' => null]);

        return response()->json(['data' => ['hero' => null]]);
    }

    public function galeria(Request $request, Restaurante $restaurante): JsonResponse
    {
        Gate::authorize('create', GaleriaImagen::class);

        if (GaleriaImagen::count() >= (int) config('menuonl.imagenes.max_galeria')) {
            return response()->json(['message' => 'La galería ya tiene el máximo de fotos ('.config('menuonl.imagenes.max_galeria').').'], 422);
        }

        $archivo = $this->archivo($request);
        $foto = GaleriaImagen::create([
            'imagen' => $this->imagenes->procesar($archivo, $restaurante->id, 'galeria'),
            'orden' => (int) GaleriaImagen::max('orden') + 1,
        ]);

        return response()->json(['data' => ['id' => $foto->id, 'url' => $this->imagenes->url($foto->imagen, 480)]], 201);
    }

    public function quitarGaleria(Restaurante $restaurante, GaleriaImagen $imagen): JsonResponse
    {
        Gate::authorize('delete', $imagen);

        $ruta = $imagen->imagen;
        $imagen->delete();
        $this->imagenes->deleteFromRestaurante($ruta, $restaurante->id);

        return response()->json(null, 204);
    }

    /**
     * The uploaded file. Size, type and pixels are checked by WebpImageService with the same messages as Filament;
     * here only the cases where PHP never delivered a usable file, each with its own message. The PHP error code goes
     * to the log, never to the person.
     */
    private function archivo(Request $request): UploadedFile
    {
        // Over post_max_size PHP drops the whole body, so the file is simply missing
        if (! $request->hasFile('imagen') && (int) $request->server('CONTENT_LENGTH') > 0 && $request->all() === []) {
            Log::warning('Subida de imagen descartada por PHP (¿post_max_size?).', ['bytes' => (int) $request->server('CONTENT_LENGTH')]);

            throw ValidationException::withMessages(['imagen' => $this->imagenes->mensajeLimiteDelServidor()]);
        }

        $archivo = $request->file('imagen');

        if ($archivo === null) {
            throw ValidationException::withMessages(['imagen' => 'Elige una imagen.']);
        }

        if (! $archivo instanceof UploadedFile) {
            throw ValidationException::withMessages(['imagen' => $this->imagenes->mensajeTipoNoPermitido()]);
        }

        if (! $archivo->isValid()) {
            Log::error('Subida de imagen fallida antes de procesarla.', ['codigo_php' => $archivo->getError()]);

            throw ValidationException::withMessages(['imagen' => $this->imagenes->mensajeParaErrorDeSubida($archivo->getError())]);
        }

        return $archivo;
    }
}
