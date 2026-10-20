<?php

namespace Database\Factories;

use App\Models\Pedido;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pedido>
 */
class PedidoFactory extends Factory
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
            'mesa' => (string) fake()->numberBetween(1, 20),
            'detalle' => fake()->numberBetween(1, 3).'x '.fake()->word(),
            'total' => fake()->randomFloat(2, 80, 900),
            'estado' => 'nuevo',
            'entregado_at' => null,
        ];
    }

    /**
     * Indicate the order is in the given state.
     */
    public function enEstado(string $estado): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => $estado,
            'entregado_at' => $estado === 'entregado' ? now() : null,
        ]);
    }
}
