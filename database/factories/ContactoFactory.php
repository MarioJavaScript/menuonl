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
    protected $model = Contacto::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurante_id' => Restaurante::factory(),
            'tipo' => fake()->randomElement(['telefono', 'whatsapp', 'email', 'direccion', 'instagram']),
            'etiqueta' => fake()->word(),
            'valor' => fake()->phoneNumber(),
            'orden' => fake()->numberBetween(1, 10),
            'visible' => true,
        ];
    }
}
