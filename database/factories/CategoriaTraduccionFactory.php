<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaTraduccion>
 */
class CategoriaTraduccionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'categoria_id' => Categoria::factory(),
            'idioma' => 'es',
            'nombre' => fake()->words(2, true),
            'descripcion' => fake()->sentence(),
        ];
    }
}
