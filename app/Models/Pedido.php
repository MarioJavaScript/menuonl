<?php

namespace App\Models;

use App\Traits\BelongsToRestaurante;
use Database\Factories\PedidoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    /** @use HasFactory<PedidoFactory> */
    use BelongsToRestaurante, HasFactory;

    /**
     * Order flow, in the only direction a ticket can move.
     */
    public const array FLUJO = ['nuevo', 'cocina', 'listo', 'entregado'];

    protected $table = 'pedidos';

    protected $fillable = [
        'restaurante_id',
        'mesa',
        'detalle',
        'total',
        'estado',
        'entregado_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'entregado_at' => 'datetime',
        ];
    }

    /**
     * State that follows the current one, or null when the order is already delivered.
     */
    public function siguienteEstado(): ?string
    {
        $posicion = array_search($this->estado, self::FLUJO, true);

        return $posicion === false ? null : (self::FLUJO[$posicion + 1] ?? null);
    }
}
