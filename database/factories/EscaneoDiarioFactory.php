<?php

namespace Database\Factories;

use App\Enums\TipoCodigoAcceso;
use App\Models\CodigoQr;
use App\Models\EscaneoDiario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EscaneoDiario>
 */
class EscaneoDiarioFactory extends Factory
{
    /**
     * Define the model's default state. El restaurante sale del código para que los dos siempre coincidan.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo_qr_id' => CodigoQr::factory(),
            'restaurante_id' => fn (array $atributos): int => (int) CodigoQr::withoutGlobalScopes()->findOrFail($atributos['codigo_qr_id'])->restaurante_id,
            'fecha' => '2026-10-20',
            'tipo' => TipoCodigoAcceso::Qr,
            'conteo' => 1,
        ];
    }

    /**
     * Escaneos de un código concreto, con su tipo actual.
     */
    public function deCodigo(CodigoQr $codigo): static
    {
        return $this->state(fn (): array => [
            'codigo_qr_id' => $codigo->getKey(),
            'restaurante_id' => $codigo->restaurante_id,
            'tipo' => $codigo->tipo,
        ]);
    }

    /**
     * Día local (Y-m-d) y cantidad de escaneos.
     */
    public function enElDia(string $fecha, int $conteo = 1): static
    {
        return $this->state(fn (): array => ['fecha' => $fecha, 'conteo' => $conteo]);
    }
}
