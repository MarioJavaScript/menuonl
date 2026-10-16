<?php

namespace Database\Factories;

use App\Models\Horario;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Horario>
 */
class HorarioFactory extends Factory
{
    protected $model = Horario::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurante_id' => Restaurante::factory(),
            'dia_semana' => fake()->numberBetween(0, 6),
            'turno' => 1,
            'abre' => '12:00:00',
            'cierra' => '22:00:00',
            'cerrado' => false,
        ];
    }

    /**
     * State for closed day.
     */
    public function cerrado(): static
    {
        return $this->state(fn (array $attributes) => [
            'abre' => null,
            'cierra' => null,
            'cerrado' => true,
        ]);
    }

    /**
     * State for overnight shift.
     */
    public function nocturno(): static
    {
        return $this->state(fn (array $attributes) => [
            'abre' => '20:00:00',
            'cierra' => '02:00:00',
            'cerrado' => false,
        ]);
    }
}
