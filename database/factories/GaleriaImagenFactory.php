<?php

namespace Database\Factories;

use App\Models\GaleriaImagen;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GaleriaImagen>
 */
class GaleriaImagenFactory extends Factory
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
            'imagen' => fn (array $attributes) => "restaurantes/{$attributes['restaurante_id']}/galeria/".fake()->unique()->lexify('????????').'.webp',
            'descripcion' => null,
            'orden' => 0,
        ];
    }
}
