<?php

namespace Database\Factories;

use App\Models\HistorialCambio;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistorialCambio>
 */
class HistorialCambioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurante_id' => Restaurante::factory(),
            'usuario_id' => null,
            'entidad' => 'producto',
            'entidad_id' => fake()->numberBetween(1, 1000),
            'accion' => 'actualizar',
            'cambios' => ['precio' => ['antes' => '85.00', 'despues' => '95.00']],
        ];
    }
}
