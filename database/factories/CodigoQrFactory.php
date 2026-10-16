<?php

namespace Database\Factories;

use App\Models\CodigoQr;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CodigoQr>
 */
class CodigoQrFactory extends Factory
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
            'nombre' => 'General',
            'mesa' => null,
            'codigo' => Str::random(10),
            'activo' => true,
        ];
    }

    /**
     * Indicate that the QR code belongs to a table.
     */
    public function deMesa(?string $mesa = null): static
    {
        return $this->state(function (array $attributes) use ($mesa) {
            $mesa ??= (string) fake()->numberBetween(1, 30);

            return [
                'nombre' => "Mesa {$mesa}",
                'mesa' => $mesa,
            ];
        });
    }

    /**
     * Indicate that the QR code was deactivated.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
