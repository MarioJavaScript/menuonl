<?php

namespace Database\Factories;

use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitacion>
 */
class InvitacionFactory extends Factory
{
    protected $model = Invitacion::class;

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
            'token' => Str::random(64),
            'invitado_por' => User::factory(),
            'expira_en' => now()->addDays(7),
            'aceptada_en' => null,
        ];
    }

    /**
     * State for expired invitation.
     */
    public function expirada(): static
    {
        return $this->state(fn (array $attributes) => [
            'expira_en' => now()->subDay(),
        ]);
    }

    /**
     * State for accepted invitation.
     */
    public function aceptada(): static
    {
        return $this->state(fn (array $attributes) => [
            'aceptada_en' => now(),
        ]);
    }
}
