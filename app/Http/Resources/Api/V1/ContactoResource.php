<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Contacto;
use App\Services\Catalogos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contacto
 */
class ContactoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tipos = Catalogos::tiposContacto();

        $url = match ($this->tipo) {
            'telefono' => str_starts_with($this->valor, 'tel:')
                ? $this->valor
                : 'tel:'.preg_replace('/[^0-9+]/', '', $this->valor),
            'whatsapp' => 'https://wa.me/'.preg_replace('/[^0-9]/', '', $this->valor),
            'email' => str_starts_with($this->valor, 'mailto:')
                ? $this->valor
                : 'mailto:'.$this->valor,
            'direccion' => str_starts_with($this->valor, 'http')
                ? $this->valor
                : 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($this->valor),
            default => (str_starts_with($this->valor, 'http://') || str_starts_with($this->valor, 'https://'))
                ? $this->valor
                : 'https://'.$this->valor,
        };

        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'tipo_nombre' => $tipos[$this->tipo] ?? $this->tipo,
            'etiqueta' => $this->etiqueta,
            'valor' => $this->valor,
            'orden' => (int) $this->orden,
            'url' => $url,
        ];
    }
}
