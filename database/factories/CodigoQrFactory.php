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
    protected $model = CodigoQr::class;

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
            'codigo' => Str::random(10),
            'tipo' => 'general',
            'mesa' => null,
            'activo' => true,
            'contador_escaneos' => 0,
            'ultimo_escaneo_en' => null,
        ];
    }

    /**
     * State for table-specific QR code.
     */
    public function paraMesa(string $numeroMesa = '1'): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => "Mesa {$numeroMesa}",
            'tipo' => 'mesa',
            'mesa' => $numeroMesa,
        ]);
    }
}
