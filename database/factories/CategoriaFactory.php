<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurante_id' => Restaurante::factory(),
            'orden' => fake()->numberBetween(1, 20),
            'visible' => true,
        ];
    }

    /**
     * Include a default Spanish translation.
     */
    public function withTranslation(string $nombre = 'Entradas', string $idioma = 'es'): static
    {
        return $this->afterCreating(function (Categoria $categoria) use ($nombre, $idioma) {
            $categoria->traducciones()->create([
                'idioma' => $idioma,
                'nombre' => $nombre,
                'descripcion' => fake()->sentence(),
            ]);
        });
    }
}
