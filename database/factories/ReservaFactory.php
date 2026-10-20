<?php

namespace Database\Factories;

use App\Models\Reserva;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reserva>
 */
class ReservaFactory extends Factory
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
            'nombre' => fake()->name(),
            'personas' => fake()->numberBetween(1, 8),
            'fecha' => now()->toDateString(),
            'hora' => fake()->randomElement(['13:00:00', '14:30:00', '20:00:00', '21:30:00']),
            'estado' => 'pendiente',
            'nota' => null,
        ];
    }

    /**
     * Indicate that the reservation was confirmed.
     */
    public function confirmada(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'confirmada']);
    }

    /**
     * Indicate that the reservation was cancelled.
     */
    public function cancelada(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'cancelada']);
    }
}
