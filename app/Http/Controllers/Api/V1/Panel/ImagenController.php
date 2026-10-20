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

/**
 * Photo uploads from the panel: dish photos (module "menu"), hero and gallery (module "ajustes").
 *
 * Every upload is validated (real MIME type, size, dimensions, no SVG), cropped to a fixed ratio and
 * stored as WebP with responsive copies, always inside the restaurant's own directory.
 */
class ImagenController extends Controller
{
    public function __construct(private WebpImageService $imagenes) {}

    public function producto(Request $request, Restaurante $restaurante, Producto $producto): JsonResponse
    {
        Gate::authorize('update', $producto);
        $archivo = $this->archivo($request);

        $producto->update(['imagen' => $this->imagenes->storeCroppedForRestaurante($archivo, $restaurante->id, 'productos', 'platillo')]);

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

        $restaurante->update(['portada' => $this->imagenes->storeCroppedForRestaurante($archivo, $restaurante->id, 'portadas', 'hero')]);

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
            'imagen' => $this->imagenes->storeCroppedForRestaurante($archivo, $restaurante->id, 'galeria', 'galeria'),
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

    private function archivo(Request $request): UploadedFile
    {
        $request->validate([
            'imagen' => ['required', 'file', 'max:'.(int) ceil(config('menuonl.imagenes.max_bytes') / 1024)],
        ], [
            'imagen.max' => 'La imagen pesa más del máximo permitido.',
            'imagen.required' => 'Elige una imagen.',
        ]);

        return $request->file('imagen');
    }
}
