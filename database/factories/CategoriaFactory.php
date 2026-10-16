<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
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
            'orden' => fake()->numberBetween(0, 100),
            'visible' => true,
        ];
    }

    /**
     * Indicate that the category is hidden from the public menu.
     */
    public function oculta(): static
    {
        return $this->state(fn (array $attributes) => [
            'visible' => false,
        ]);
    }
}
