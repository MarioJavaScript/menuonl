<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\ProductoTraduccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductoTraduccion>
 */
class ProductoTraduccionFactory extends Factory
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
            'idioma' => 'es',
            'nombre' => fake()->words(3, true),
            'descripcion' => fake()->sentence(),
        ];
    }
}
