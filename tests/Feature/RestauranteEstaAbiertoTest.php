<?php

namespace Tests\Feature;

use App\Models\Horario;
use App\Models\Restaurante;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RestauranteEstaAbiertoTest extends TestCase
{
    use RefreshDatabase;

    private const ZONA = 'America/Mexico_City';

    private const MARTES = 2;

    private const VIERNES = 5;

    /**
     * Each case: shifts as [dia_semana, abre, cierra], local time in the restaurant's zone, expected state.
     * 2026-10-20 is a Tuesday; 2026-10-23 a Friday.
     *
     * @return array<string, array{list<array{int, string, string}>, string, bool}>
     */
    public static function horariosYMomentos(): array
    {
        $turnoPartido = [[self::MARTES, '13:00', '17:00'], [self::MARTES, '19:00', '23:00']];
        $turnoNocturno = [[self::VIERNES, '20:00', '02:00']];
        $cierraAMedianoche = [[self::VIERNES, '18:00', '00:00']];

        return [
            'turno partido, antes de abrir' => [$turnoPartido, '2026-10-20 12:59', false],
            'turno partido, primer turno' => [$turnoPartido, '2026-10-20 13:00', true],
            'turno partido, entre turnos' => [$turnoPartido, '2026-10-20 18:00', false],
            'turno partido, segundo turno' => [$turnoPartido, '2026-10-20 22:59', true],
            'turno partido, a la hora de cierre' => [$turnoPartido, '2026-10-20 23:00', false],
            'turno partido, otro dia de la semana' => [$turnoPartido, '2026-10-21 15:00', false],
            'turno nocturno, antes de abrir' => [$turnoNocturno, '2026-10-23 19:59', false],
            'turno nocturno, antes de medianoche' => [$turnoNocturno, '2026-10-23 23:30', true],
            'turno nocturno, despues de medianoche' => [$turnoNocturno, '2026-10-24 01:30', true],
            'turno nocturno, a la hora de cierre' => [$turnoNocturno, '2026-10-24 02:00', false],
            'turno nocturno, no se extiende al dia siguiente' => [$turnoNocturno, '2026-10-24 21:00', false],
            'cierra a medianoche, ultimo minuto' => [$cierraAMedianoche, '2026-10-23 23:59', true],
            'cierra a medianoche, al dar la medianoche' => [$cierraAMedianoche, '2026-10-24 00:00', false],
        ];
    }

    /**
     * @param  list<array{int, string, string}>  $turnos
     */
    #[DataProvider('horariosYMomentos')]
    public function test_calcula_si_esta_abierto(array $turnos, string $horaLocal, bool $abierto): void
    {
        $restaurante = $this->restauranteConTurnos($turnos);

        $this->assertSame($abierto, $restaurante->estaAbiertoAhora(Carbon::parse($horaLocal, self::ZONA)));
    }

    public function test_dia_marcado_como_cerrado_ignora_sus_turnos(): void
    {
        $restaurante = $this->restauranteConTurnos([[self::MARTES, '13:00', '17:00']]);
        Horario::factory()->cerrado()->create(['restaurante_id' => $restaurante->id, 'dia_semana' => self::MARTES]);

        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse('2026-10-20 15:00', self::ZONA)));
    }

    public function test_usa_la_zona_horaria_del_restaurante(): void
    {
        $restaurante = $this->restauranteConTurnos([[self::MARTES, '13:00', '17:00']]);

        // 21:00 UTC is 15:00 in Mexico City (UTC-6).
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse('2026-10-20 21:00', 'UTC')));
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse('2026-10-20 15:00', 'UTC')));
    }

    public function test_sin_horarios_esta_cerrado(): void
    {
        $restaurante = Restaurante::factory()->create(['zona_horaria' => self::ZONA]);

        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse('2026-10-20 15:00', self::ZONA)));
    }

    /**
     * @param  list<array{int, string, string}>  $turnos
     */
    private function restauranteConTurnos(array $turnos): Restaurante
    {
        $restaurante = Restaurante::factory()->create(['zona_horaria' => self::ZONA]);

        foreach ($turnos as [$dia, $abre, $cierra]) {
            Horario::factory()->create([
                'restaurante_id' => $restaurante->id,
                'dia_semana' => $dia,
                'abre' => $abre,
                'cierra' => $cierra,
            ]);
        }

        return $restaurante;
    }
}
