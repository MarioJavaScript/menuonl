<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\ProductoVariante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductoVariante>
 */
class ProductoVarianteFactory extends Factory
{
    protected $model = ProductoVariante::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'precio' => fake()->randomFloat(2, 50, 200),
            'disponible' => true,
            'orden' => fake()->numberBetween(1, 5),
        ];
    }

    /**
     * Include a default translation.
     */
    public function withTranslation(string $nombre = 'Grande', string $idioma = 'es'): static
    {
        return $this->afterCreating(function (ProductoVariante $variante) use ($nombre, $idioma) {
            $variante->traducciones()->create([
                'idioma' => $idioma,
                'nombre' => $nombre,
            ]);
        });
    }
}
