<?php

namespace Database\Factories;

use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestauranteIdioma>
 */
class RestauranteIdiomaFactory extends Factory
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
            'idioma' => 'es',
            'orden' => 0,
        ];
    }
}
