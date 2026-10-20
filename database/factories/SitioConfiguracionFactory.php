<?php

namespace Database\Factories;

use App\Models\Restaurante;
use App\Models\SitioConfiguracion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SitioConfiguracion>
 */
class SitioConfiguracionFactory extends Factory
{
    /**
     * Define the model's default state (sin imágenes: se asignan en el test con rutas propias del restaurante).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurante_id' => Restaurante::factory(),
            'hero_subtitulo' => fake()->sentence(4),
            'historia_etiqueta' => 'Nuestra historia',
            'historia_titulo' => fake()->sentence(4),
            'historia_parrafo_1' => fake()->paragraph(),
            'historia_parrafo_2' => fake()->paragraph(),
            'estadisticas' => [],
            'reservas_activas' => true,
        ];
    }
}
