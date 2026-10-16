<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Restaurante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurante_id' => Restaurante::factory(),
            'categoria_id' => fn (array $attributes) => Categoria::factory()->state([
                'restaurante_id' => $attributes['restaurante_id'],
            ]),
            'precio' => fake()->randomFloat(2, 50, 400),
            'precio_oferta' => null,
            'foto_url' => null,
            'disponible' => true,
            'visible' => true,
            'destacado' => false,
            'orden' => fake()->numberBetween(1, 30),
        ];
    }

    /**
     * Include a default translation.
     */
    public function withTranslation(string $nombre = 'Tacos', string $idioma = 'es'): static
    {
        return $this->afterCreating(function (Producto $producto) use ($nombre, $idioma) {
            $producto->traducciones()->create([
                'idioma' => $idioma,
                'nombre' => $nombre,
                'descripcion' => fake()->paragraph(),
            ]);
        });
    }

    /**
     * Set product on sale.
     */
    public function enOferta(float $descuento = 0.2): static
    {
        return $this->state(function (array $attributes) use ($descuento) {
            $precio = (float) $attributes['precio'];

            return [
                'precio_oferta' => round($precio * (1 - $descuento), 2),
            ];
        });
    }

    /**
     * Mark product as sold out.
     */
    public function agotado(): static
    {
        return $this->state(fn (array $attributes) => [
            'disponible' => false,
        ]);
    }
}
