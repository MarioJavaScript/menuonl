<?php

namespace Database\Factories;

use App\Models\Horario;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Horario>
 */
class HorarioFactory extends Factory
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
            'dia_semana' => fake()->numberBetween(0, 6),
            'abre' => '09:00:00',
            'cierra' => '18:00:00',
            'cerrado' => false,
        ];
    }

    /**
     * Indicate that the restaurant does not open that day.
     */
    public function cerrado(): static
    {
        return $this->state(fn (array $attributes) => [
            'abre' => null,
            'cierra' => null,
            'cerrado' => true,
        ]);
    }
}
