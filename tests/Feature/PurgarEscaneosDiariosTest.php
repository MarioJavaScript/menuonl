<?php

namespace Tests\Feature;

use App\Models\CodigoQr;
use App\Models\EscaneoDiario;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Retención de los conteos diarios: menuonl:purgar-escaneos borra lo anterior a 400 días (mínimo 31).
 */
class PurgarEscaneosDiariosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-25 12:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function hace(int $dias): string
    {
        return Carbon::now(config('app.timezone'))->subDays($dias)->toDateString();
    }

    public function test_borra_lo_anterior_al_corte_y_conserva_el_dia_del_corte_y_lo_reciente(): void
    {
        $codigo = CodigoQr::factory()->deMesa('1')->create();
        foreach ([401, 400, 399, 0] as $dias) {
            EscaneoDiario::factory()->deCodigo($codigo)->enElDia($this->hace($dias))->create();
        }

        $this->artisan('menuonl:purgar-escaneos')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$this->hace(400), $this->hace(399), $this->hace(0)],
            EscaneoDiario::withoutGlobalScopes()->get()->map(fn (EscaneoDiario $fila): string => $fila->fecha->toDateString())->all(),
        );
    }

    public function test_borra_en_lotes_cuando_hay_mas_de_mil_filas(): void
    {
        $codigo = CodigoQr::factory()->deMesa('1')->create();
        $filas = [];
        for ($i = 0; $i < 1250; $i++) {
            $filas[] = [
                'restaurante_id' => $codigo->restaurante_id,
                'codigo_qr_id' => $codigo->id,
                'fecha' => Carbon::parse($this->hace(500))->subDays($i)->toDateString(),
                'tipo' => 'qr',
                'conteo' => 1,
            ];
        }
        foreach (array_chunk($filas, 400) as $lote) {
            EscaneoDiario::withoutGlobalScopes()->insert($lote);
        }
        EscaneoDiario::factory()->deCodigo($codigo)->enElDia($this->hace(1))->create();

        $this->artisan('menuonl:purgar-escaneos')->assertSuccessful();

        $this->assertSame(1, EscaneoDiario::withoutGlobalScopes()->count());
    }

    public function test_la_retencion_configurada_nunca_baja_de_31_dias(): void
    {
        config(['menuonl.metricas.retencion_dias' => 1]);
        $codigo = CodigoQr::factory()->deMesa('1')->create();
        foreach ([32, 31, 30] as $dias) {
            EscaneoDiario::factory()->deCodigo($codigo)->enElDia($this->hace($dias))->create();
        }

        $this->artisan('menuonl:purgar-escaneos')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$this->hace(31), $this->hace(30)],
            EscaneoDiario::withoutGlobalScopes()->get()->map(fn (EscaneoDiario $fila): string => $fila->fecha->toDateString())->all(),
        );
    }

    public function test_la_retencion_por_defecto_es_de_400_dias(): void
    {
        $this->assertSame(400, config('menuonl.metricas.retencion_dias'));
    }

    public function test_la_purga_esta_programada_cada_dia_a_las_4_30(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($evento): bool => str_contains((string) $evento->command, 'menuonl:purgar-escaneos'));

        $this->assertNotNull($evento, 'menuonl:purgar-escaneos no está programado.');
        $this->assertSame('30 4 * * *', $evento->expression);
        $this->assertTrue($evento->withoutOverlapping);
    }
}
