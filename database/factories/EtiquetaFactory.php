<?php

namespace Database\Factories;

use App\Models\Etiqueta;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Etiqueta>
 */
class EtiquetaFactory extends Factory
{
    protected $model = Etiqueta::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'nombre' => ucfirst($name),
            'slug' => Str::slug($name),
            'codigo' => Str::slug($name),
            'icono' => 'tag',
            'color' => '#16a34a',
            'activo' => true,
        ];
    }
}
