<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductoVariante>
 */
class ProductoVarianteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'precio' => fake()->randomFloat(2, 30, 300),
            'disponible' => true,
            'orden' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Indicate that the variant is sold out.
     */
    public function agotada(): static
    {
        return $this->state(fn (array $attributes) => [
            'disponible' => false,
        ]);
    }
}
