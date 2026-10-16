<?php

namespace Database\Factories;

use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Restaurante>
 */
class RestauranteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->company(),
            'slug' => fake()->unique()->slug(2),
            'descripcion' => fake()->sentence(),
            'moneda' => 'MXN',
            'idioma_default' => 'es',
            'zona_horaria' => 'America/Mexico_City',
            'whatsapp_pedidos' => fake()->numerify('52##########'),
            'pedidos_activos' => false,
            'mostrar_agotados' => true,
            'estado' => 'activo',
        ];
    }

    /**
     * Indicate that the restaurant is suspended by the platform.
     */
    public function suspendido(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'suspendido',
        ]);
    }
}
