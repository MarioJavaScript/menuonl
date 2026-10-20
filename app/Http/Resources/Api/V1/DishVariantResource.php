<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductoVariante;
use App\Services\FormatoMoneda;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductoVariante
 */
class DishVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $idioma = (string) $request->attributes->get('idioma', app()->getLocale());
        $moneda = (string) $request->attributes->get('moneda', $this->producto?->restaurante?->moneda ?? 'MXN');

        $traduccion = $this->traduccion($idioma);
        if ($traduccion === null && $this->relationLoaded('traducciones')) {
            $traduccion = $this->traducciones->firstWhere('idioma', config('app.fallback_locale', 'es'))
                ?? $this->traducciones->first();
        }

        $precio = (float) $this->precio;

        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'nombre' => $traduccion?->nombre ?? 'Opción',
            'precio' => $precio,
            'precio_formateado' => FormatoMoneda::formatear($precio, $moneda, $idioma),
            'disponible' => (bool) $this->disponible,
            'orden' => (int) $this->orden,
        ];
    }
}
