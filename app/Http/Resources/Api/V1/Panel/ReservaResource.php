<?php

namespace App\Http\Resources\Api\V1\Panel;

use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reserva
 */
class ReservaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'personas' => $this->personas,
            'fecha' => $this->fecha->toDateString(),
            'hora' => substr((string) $this->hora, 0, 5),
            'estado' => $this->estado,
            'nota' => $this->nota,
        ];
    }
}
