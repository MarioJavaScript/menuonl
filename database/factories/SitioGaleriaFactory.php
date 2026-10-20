<?php

namespace Database\Factories;

use App\Models\Restaurante;
use App\Models\SitioGaleria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SitioGaleria>
 */
class SitioGaleriaFactory extends Factory
{
    /**
     * Define the model's default state. La imagen debe pasarse con una ruta restaurantes/{id}/sitio/...
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurante_id' => Restaurante::factory(),
            'imagen' => 'restaurantes/0/sitio/pendiente.webp',
            'titulo' => fake()->words(3, true),
            'texto_alternativo' => fake()->sentence(6),
            'creditos' => null,
            'orden' => fake()->numberBetween(0, 10),
            'visible' => true,
        ];
    }
}
