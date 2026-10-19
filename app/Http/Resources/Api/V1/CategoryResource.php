<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Categoria
 */
class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $idioma = (string) $request->attributes->get('idioma', app()->getLocale());

        $traduccion = $this->traduccion($idioma);
        if ($traduccion === null && $this->relationLoaded('traducciones')) {
            $traduccion = $this->traducciones->firstWhere('idioma', config('app.fallback_locale', 'es'))
                ?? $this->traducciones->first();
        }

        return [
            'id' => $this->id,
            'orden' => (int) $this->orden,
            'nombre' => $traduccion?->nombre ?? 'Categoría',
            'descripcion' => $traduccion?->descripcion,
            'platillos' => DishResource::collection($this->whenLoaded('productos')),
        ];
    }
}
