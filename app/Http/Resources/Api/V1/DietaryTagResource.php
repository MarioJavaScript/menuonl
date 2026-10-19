<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Etiqueta;
use App\Services\Catalogos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Etiqueta
 */
class DietaryTagResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => Catalogos::nombreEtiqueta($this->codigo),
            'icono' => $this->icono ?? Etiqueta::CATALOGO[$this->codigo] ?? null,
        ];
    }
}
