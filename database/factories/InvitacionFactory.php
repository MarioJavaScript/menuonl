<?php

namespace Database\Factories;

use App\Models\Invitacion;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitacion>
 */
class InvitacionFactory extends Factory
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
            'email' => fake()->unique()->safeEmail(),
            'rol' => 'editor',
            'token' => hash('sha256', Str::random(40)),
            'invitado_por' => null,
            'expira_en' => now()->addDays(7),
            'aceptada_en' => null,
        ];
    }

    /**
     * Indicate that the invitation is past its expiration date.
     */
    public function expirada(): static
    {
        return $this->state(fn (array $attributes) => [
            'expira_en' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the invitation was already accepted.
     */
    public function aceptada(): static
    {
        return $this->state(fn (array $attributes) => [
            'aceptada_en' => now(),
        ]);
    }
}
