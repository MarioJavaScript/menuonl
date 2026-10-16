<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
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
            'categoria_id' => fn (array $attributes) => Categoria::factory()->state([
                'restaurante_id' => $attributes['restaurante_id'],
            ]),
            'precio' => fake()->randomFloat(2, 30, 300),
            'precio_oferta' => null,
            'imagen' => null,
            'disponible' => true,
            'visible' => true,
            'destacado' => false,
            'orden' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the product is sold out.
     */
    public function agotado(): static
    {
        return $this->state(fn (array $attributes) => [
            'disponible' => false,
        ]);
    }

    /**
     * Indicate that the product has a discounted price.
     */
    public function enOferta(): static
    {
        return $this->state(fn (array $attributes) => [
            'precio_oferta' => round($attributes['precio'] * 0.8, 2),
        ]);
    }

    /**
     * Indicate that the product is hidden from the public menu.
     */
    public function oculto(): static
    {
        return $this->state(fn (array $attributes) => [
            'visible' => false,
        ]);
    }

    /**
     * Indicate that the product appears in "Recomendados".
     */
    public function destacado(): static
    {
        return $this->state(fn (array $attributes) => [
            'destacado' => true,
        ]);
    }
}
