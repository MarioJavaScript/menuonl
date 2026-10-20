<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Producto;
use App\Services\FormatoMoneda;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Producto
 */
class DishResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $idioma = (string) $request->attributes->get('idioma', app()->getLocale());
        $moneda = (string) $request->attributes->get('moneda', $this->restaurante?->moneda ?? 'MXN');

        $traduccion = $this->traduccion($idioma);
        if ($traduccion === null && $this->relationLoaded('traducciones')) {
            $traduccion = $this->traducciones->firstWhere('idioma', config('app.fallback_locale', 'es'))
                ?? $this->traducciones->first();
        }

        $precio = (float) $this->precio;
        $tieneOferta = $this->precio_oferta !== null && (float) $this->precio_oferta < $precio;
        $precioOferta = $tieneOferta ? (float) $this->precio_oferta : null;
        $precioVigente = (float) $this->precioVigente();

        return [
            'id' => $this->id,
            'categoria_id' => $this->categoria_id,
            'nombre' => $traduccion?->nombre ?? 'Platillo',
            'descripcion' => $traduccion?->descripcion,
            'precio' => $precio,
            'precio_formateado' => FormatoMoneda::formatear($precio, $moneda, $idioma),
            'precio_oferta' => $precioOferta,
            'precio_oferta_formateado' => $precioOferta !== null
                ? FormatoMoneda::formatear($precioOferta, $moneda, $idioma)
                : null,
            'precio_vigente' => $precioVigente,
            'precio_vigente_formateado' => FormatoMoneda::formatear($precioVigente, $moneda, $idioma),
            'tiene_oferta' => $tieneOferta,
            'tiene_variantes' => $this->tieneVariantes(),
            'imagen_url' => filled($this->imagen) ? Storage::url($this->imagen) : null,
            'disponible' => (bool) $this->disponible,
            'destacado' => (bool) $this->destacado,
            'orden' => (int) $this->orden,
            'etiquetas' => DietaryTagResource::collection($this->whenLoaded('etiquetas')),
            'variantes' => DishVariantResource::collection($this->whenLoaded('variantes')),
        ];
    }
}
