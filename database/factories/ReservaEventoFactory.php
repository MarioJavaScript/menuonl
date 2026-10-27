<?php

namespace Database\Factories;

use App\Models\Reserva;
use App\Models\ReservaEvento;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservaEvento>
 */
class ReservaEventoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurante_id' => Restaurante::factory(),
            'reserva_id' => fn (array $atributos): int => Reserva::factory()->create(['restaurante_id' => $atributos['restaurante_id']])->id,
            'usuario_id' => null,
            'accion' => 'confirmada',
            'origen' => 'admin',
            'estado_antes' => 'pendiente',
            'estado_despues' => 'confirmada',
        ];
    }
}
