<?php

namespace Database\Factories;

use App\Models\Contacto;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contacto>
 */
class ContactoFactory extends Factory
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
            'tipo' => 'telefono',
            'etiqueta' => null,
            'valor' => fake()->numerify('+52 55 #### ####'),
            'orden' => fake()->numberBetween(0, 10),
            'visible' => true,
        ];
    }
}
