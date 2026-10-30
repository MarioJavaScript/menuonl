<?php

namespace App\Models;

use App\Traits\InvalidaElMenuDelRestaurante;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Fila de producto_etiqueta. Existe para que asignar o quitar una etiqueta (attach, detach o sync) dispare eventos y vacíe la
 * caché del menú público; con el pivote genérico de Eloquent esos cambios no avisan a nadie.
 */
class ProductoEtiqueta extends Pivot
{
    use InvalidaElMenuDelRestaurante;

    protected $table = 'producto_etiqueta';

    public $timestamps = false;

    public function restauranteIdDelMenu(): ?int
    {
        $restauranteId = Producto::withoutGlobalScopes()->whereKey($this->producto_id)->value('restaurante_id');

        return $restauranteId === null ? null : (int) $restauranteId;
    }
}
