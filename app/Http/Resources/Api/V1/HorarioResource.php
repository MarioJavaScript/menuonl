<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Horario;
use App\Services\Catalogos;
use App\Services\FormatoFecha;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Horario
 */
class HorarioResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dias = Catalogos::diasSemana();

        return [
            'id' => $this->id,
            'dia_semana' => (int) $this->dia_semana,
            'dia_nombre' => $dias[$this->dia_semana] ?? '',
            'abre' => $this->abre,
            'cierra' => $this->cierra,
            'abre_formateado' => $this->abre ? FormatoFecha::hora($this->abre) : null,
            'cierra_formateado' => $this->cierra ? FormatoFecha::hora($this->cierra) : null,
            'rango_formateado' => ! $this->cerrado && $this->abre && $this->cierra
                ? FormatoFecha::rango($this->abre, $this->cierra)
                : 'Cerrado',
            'cerrado' => (bool) $this->cerrado,
        ];
    }
}
