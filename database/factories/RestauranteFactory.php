<?php

namespace Database\Factories;

use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Restaurante>
 */
class RestauranteFactory extends Factory
{
    protected $model = Restaurante::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'nombre' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'descripcion' => fake()->sentence(),
            'moneda' => 'MXN',
            'idioma_default' => 'es',
            'zona_horaria' => 'America/Mexico_City',
            'whatsapp_pedidos' => '521'.fake()->numerify('##########'),
            'pedidos_activos' => true,
            'mostrar_agotados' => true,
            'estado' => 'activo',
        ];
    }
}
