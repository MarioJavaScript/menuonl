<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Restaurante;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Restaurante
 */
class RestaurantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $estaAbierto = $this->estaAbiertoAhora();
        $puedePedir = (bool) ($this->pedidos_activos && $estaAbierto && filled($this->whatsapp_pedidos));
        $whatsappLimpio = filled($this->whatsapp_pedidos)
            ? preg_replace('/[^0-9]/', '', (string) $this->whatsapp_pedidos)
            : null;

        $idioma = (string) $request->attributes->get('idioma', app()->getLocale());
        $idiomasDisponibles = (array) $request->attributes->get('idiomas_disponibles', [$this->idioma_default]);
        $mesa = $request->attributes->get('mesa');
        $destacados = $request->attributes->get('destacados', $this->destacados ?? collect());

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'eslogan' => $this->eslogan,
            'descripcion' => $this->descripcion,
            'logo_url' => filled($this->logo) ? Storage::url($this->logo) : null,
            'portada_url' => filled($this->portada) ? Storage::url($this->portada) : null,
            'monograma' => mb_strtoupper(mb_substr(trim($this->nombre), 0, 1, 'UTF-8'), 'UTF-8'),
            'tema' => $this->temaVigente(),
            'color_acento' => $this->colorAcentoSeguro(),
            'color_acento_texto' => $this->colorAcentoTexto(),
            'moneda' => $this->moneda,
            'idioma' => $idioma,
            'idioma_default' => $this->idioma_default,
            'idiomas_disponibles' => $idiomasDisponibles,
            'zona_horaria' => $this->zona_horaria,
            'abierto' => $estaAbierto,
            'pedidos_activos' => (bool) $this->pedidos_activos,
            'puede_pedir' => $puedePedir,
            'whatsapp_pedidos' => $this->whatsapp_pedidos,
            'whatsapp_url' => $whatsappLimpio ? 'https://wa.me/'.$whatsappLimpio : null,
            'mostrar_agotados' => (bool) $this->mostrar_agotados,
            'mesa' => $mesa,
            'horarios' => HorarioResource::collection($this->whenLoaded('horarios')),
            'contactos' => ContactoResource::collection($this->whenLoaded('contactos')),
            'categorias' => CategoryResource::collection($this->whenLoaded('categorias')),
            'destacados' => DishResource::collection($destacados),
        ];
    }
}
