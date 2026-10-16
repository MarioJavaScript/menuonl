<?php

namespace Database\Factories;

use App\Models\ProductoVariante;
use App\Models\VarianteTraduccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VarianteTraduccion>
 */
class VarianteTraduccionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'variante_id' => ProductoVariante::factory(),
            'idioma' => 'es',
            'nombre' => fake()->randomElement(['Chico', 'Mediano', 'Grande']),
        ];
    }
}
