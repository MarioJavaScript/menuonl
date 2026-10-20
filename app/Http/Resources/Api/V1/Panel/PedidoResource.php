<?php

namespace App\Http\Resources\Api\V1\Panel;

use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pedido
 */
class PedidoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mesa' => $this->mesa,
            'detalle' => $this->detalle,
            'total' => (float) $this->total,
            'estado' => $this->estado,
            'minutos' => (int) $this->created_at->diffInMinutes(now(), true),
        ];
    }
}
