<?php

namespace Tests\Feature;

use App\Models\HistorialCambio;
use App\Models\Reserva;
use App\Models\ReservaEvento;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Services\ConflictoDeReserva;
use App\Services\GestionDeReservas;
use App\Services\MenuCacheService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Historial de reservas (reserva_eventos): quién confirmó, canceló, movió o creó, escrito por GestionDeReservas en la misma
 * transacción que el cambio; sin datos del comensal; sin tocar la caché del menú; con retención configurable y purga programada.
 */
class ReservaEventosTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private GestionDeReservas $gestion;

    private Restaurante $restaurante;

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 18:00:00', 'UTC'));
        $this->setUpEscenarioDePanel();
        $this->gestion = app(GestionDeReservas::class);
        $this->restaurante = $this->crearRestaurante('la-esquina');
        $this->propietario = $this->crearUsuario('propietario', $this->restaurante);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function reserva(string $estado = 'pendiente'): Reserva
    {
        return Reserva::factory()->create([
            'restaurante_id' => $this->restaurante->id,
            'estado' => $estado,
            'fecha' => '2026-10-21',
            'hora' => '20:00:00',
            'telefono' => '5512345678',
            'nota' => 'Alergia a las nueces',
        ]);
    }

    public function test_cada_accion_registra_quien_que_y_desde_donde(): void
    {
        $reserva = $this->reserva();

        $this->gestion->confirmar($this->propietario, $this->restaurante, $reserva, 'pendiente', 'admin');
        $this->gestion->marcarLlegada($this->propietario, $this->restaurante, $reserva);
        $this->gestion->cancelar($this->propietario, $this->restaurante, $reserva, origen: 'panel');
        $this->gestion->restaurar($this->propietario, $this->restaurante, $reserva);

        $eventos = ReservaEvento::where('reserva_id', $reserva->id)->orderBy('id')->get();

        $this->assertSame(['confirmada', 'llego', 'cancelada', 'restaurada'], $eventos->pluck('accion')->all());
        $this->assertSame(['admin', 'api', 'panel', 'api'], $eventos->pluck('origen')->all());
        $this->assertSame([$this->propietario->id], $eventos->pluck('usuario_id')->unique()->values()->all());
        $this->assertSame(['pendiente', 'confirmada', 'llego', 'cancelada'], $eventos->pluck('estado_antes')->all());
        $this->assertSame(['confirmada', 'llego', 'cancelada', 'pendiente'], $eventos->pluck('estado_despues')->all());
        $this->assertSame([$this->restaurante->id], $eventos->pluck('restaurante_id')->unique()->values()->all());
    }

    public function test_mover_registra_hora_y_fecha_de_antes_y_despues_y_solo_lo_que_cambio(): void
    {
        $reserva = $this->reserva('confirmada');

        $this->gestion->mover($this->propietario, $this->restaurante, $reserva, '21:30', null, origen: 'admin');
        $this->gestion->mover($this->propietario, $this->restaurante, $reserva, '21:30', '2026-10-25', ['hora' => (string) $reserva->fresh()->getRawOriginal('hora')], 'admin');

        [$primero, $segundo] = ReservaEvento::where('reserva_id', $reserva->id)->orderBy('id')->get()->all();

        $this->assertSame('movida', $primero->accion);
        $this->assertSame(['20:00', '21:30'], [$primero->hora_antes, $primero->hora_despues]);
        $this->assertNull($primero->fecha_antes);
        $this->assertNull($primero->estado_antes);

        $this->assertSame('movida', $segundo->accion);
        $this->assertNull($segundo->hora_antes);
        $this->assertSame(['2026-10-21', '2026-10-25'], [$segundo->fecha_antes->toDateString(), $segundo->fecha_despues->toDateString()]);
    }

    public function test_crear_y_eliminar_guardan_el_estado_completo(): void
    {
        // Crear se autoriza contra el restaurante activo (el contexto del panel o de la API).
        RestauranteScope::setRestauranteId($this->restaurante->id);

        $reserva = $this->gestion->crear($this->propietario, $this->restaurante, ['nombre' => 'Ana', 'personas' => 2, 'hora' => '19:00'], 'admin');
        $this->gestion->eliminar($this->propietario, $this->restaurante, $reserva, 'admin');

        [$creada, $eliminada] = ReservaEvento::where('reserva_id', $reserva->id)->orderBy('id')->get()->all();

        $this->assertSame('creada', $creada->accion);
        $this->assertSame([null, 'confirmada', null, '19:00'], [$creada->estado_antes, $creada->estado_despues, $creada->hora_antes, $creada->hora_despues]);
        $this->assertSame('eliminada', $eliminada->accion);
        $this->assertSame(['confirmada', null, '19:00', null], [$eliminada->estado_antes, $eliminada->estado_despues, $eliminada->hora_antes, $eliminada->hora_despues]);
    }

    public function test_un_cambio_que_no_cambia_nada_no_deja_evento(): void
    {
        $reserva = $this->reserva('confirmada');

        $this->gestion->actualizar($this->propietario, $this->restaurante, $reserva, ['nota' => 'Otra nota', 'personas' => 6]);
        $this->gestion->confirmar($this->propietario, $this->restaurante, $reserva);

        $this->assertSame(0, ReservaEvento::count());
    }

    public function test_el_historial_no_guarda_datos_del_comensal(): void
    {
        $reserva = $this->reserva();
        $reserva->forceFill(['nombre' => 'Nombre Secreto'])->save();
        $this->gestion->confirmar($this->propietario, $this->restaurante, $reserva);
        $this->gestion->mover($this->propietario, $this->restaurante, $reserva, '22:00');

        $columnas = Schema::getColumnListing('reserva_eventos');
        foreach (['nombre', 'telefono', 'nota', 'personas'] as $prohibida) {
            $this->assertNotContains($prohibida, $columnas);
        }

        $volcado = json_encode(ReservaEvento::all()->toArray());
        foreach (['Nombre Secreto', '5512345678', 'nueces'] as $dato) {
            $this->assertStringNotContainsString($dato, (string) $volcado);
        }
    }

    public function test_un_conflicto_no_deja_evento_ni_cambia_la_reserva(): void
    {
        $reserva = $this->reserva('pendiente');

        $this->expectException(ConflictoDeReserva::class);

        try {
            $this->gestion->confirmar($this->propietario, $this->restaurante, $reserva, 'cancelada', 'admin');
        } finally {
            $this->assertSame('pendiente', $reserva->fresh()->estado);
            $this->assertSame(0, ReservaEvento::count());
        }
    }

    public function test_si_falla_el_evento_se_deshace_el_cambio(): void
    {
        $reserva = $this->reserva('pendiente');
        ReservaEvento::creating(fn () => throw new \RuntimeException('falla el historial'));

        try {
            $this->gestion->confirmar($this->propietario, $this->restaurante, $reserva);
            $this->fail('Debió fallar.');
        } catch (\RuntimeException $e) {
            $this->assertSame('falla el historial', $e->getMessage());
        }

        $this->assertSame('pendiente', $reserva->fresh()->estado);
        ReservaEvento::flushEventListeners();
    }

    public function test_un_origen_desconocido_se_rechaza_antes_de_escribir(): void
    {
        $reserva = $this->reserva();

        try {
            $this->gestion->confirmar($this->propietario, $this->restaurante, $reserva, null, 'telegram');
            $this->fail('Debió rechazar el origen.');
        } catch (InvalidArgumentException) {
            $this->assertSame('pendiente', $reserva->fresh()->estado);
        }
    }

    public function test_la_api_actual_registra_el_evento_con_origen_api(): void
    {
        $reserva = $this->reserva();

        $this->actingAs($this->propietario)
            ->patchJson("/api/v1/panel/{$this->restaurante->slug}/reservas/{$reserva->id}", ['estado' => 'confirmada'])
            ->assertOk();

        $evento = ReservaEvento::where('reserva_id', $reserva->id)->firstOrFail();
        $this->assertSame(['confirmada', 'api', $this->propietario->id], [$evento->accion, $evento->origen, $evento->usuario_id]);
    }

    public function test_no_toca_la_cache_del_menu_ni_el_historial_de_cambios_general(): void
    {
        $this->mock(MenuCacheService::class)->shouldNotReceive('invalidateRestaurante');
        $reserva = $this->reserva();

        $this->gestion->confirmar($this->propietario, $this->restaurante, $reserva);
        $this->gestion->mover($this->propietario, $this->restaurante, $reserva, '22:00');

        $this->assertSame(0, HistorialCambio::withoutGlobalScopes()->where('entidad', 'reserva')->count());
        $this->assertSame(2, ReservaEvento::count());
    }

    public function test_el_historial_de_un_restaurante_no_se_mezcla_con_el_de_otro(): void
    {
        $this->gestion->confirmar($this->propietario, $this->restaurante, $this->reserva());

        $otro = $this->crearRestaurante('otro-lugar');
        $this->assertSame(0, ReservaEvento::withoutGlobalScopes()->where('restaurante_id', $otro->id)->count());
        $this->assertSame(1, ReservaEvento::withoutGlobalScopes()->where('restaurante_id', $this->restaurante->id)->count());
    }

    public function test_el_nombre_del_usuario_se_pierde_con_gracia_si_se_borra_la_cuenta(): void
    {
        $this->gestion->confirmar($this->propietario, $this->restaurante, $this->reserva());
        $this->propietario->delete();

        $this->assertNull(ReservaEvento::firstOrFail()->usuario_id);
    }

    // ---------------------------------------------------------------- retención y purga

    private function eventoDe(string $creado): ReservaEvento
    {
        $evento = ReservaEvento::factory()->create(['restaurante_id' => $this->restaurante->id]);
        ReservaEvento::withoutGlobalScopes()->whereKey($evento->id)->toBase()->update(['created_at' => $creado]);

        return $evento;
    }

    public function test_la_purga_borra_lo_anterior_a_doce_meses_y_conserva_el_resto(): void
    {
        $viejo = $this->eventoDe('2025-10-20 17:59:59');
        $limite = $this->eventoDe('2025-10-21 18:00:00');
        $reciente = $this->eventoDe('2026-10-01 10:00:00');

        $this->artisan('menuonl:purgar-reserva-eventos')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$limite->id, $reciente->id],
            ReservaEvento::withoutGlobalScopes()->pluck('id')->all(),
        );
        $this->assertNull(ReservaEvento::withoutGlobalScopes()->find($viejo->id));
    }

    public function test_la_retencion_es_configurable_y_nunca_baja_de_un_mes(): void
    {
        $this->assertSame(12, config('menuonl.reservas.eventos_retencion_meses'));

        $hace3 = $this->eventoDe('2026-07-01 10:00:00');
        $hace1 = $this->eventoDe('2026-10-10 10:00:00');
        config(['menuonl.reservas.eventos_retencion_meses' => 3]);

        $this->artisan('menuonl:purgar-reserva-eventos')->assertSuccessful();
        $this->assertNull(ReservaEvento::withoutGlobalScopes()->find($hace3->id));
        $this->assertNotNull(ReservaEvento::withoutGlobalScopes()->find($hace1->id));

        config(['menuonl.reservas.eventos_retencion_meses' => 0]);
        $viejoDeDosMeses = $this->eventoDe('2026-08-01 10:00:00');
        $this->artisan('menuonl:purgar-reserva-eventos')->assertSuccessful();
        $this->assertNull(ReservaEvento::withoutGlobalScopes()->find($viejoDeDosMeses->id));
        $this->assertNotNull(ReservaEvento::withoutGlobalScopes()->find($hace1->id));
    }

    public function test_la_purga_borra_en_lotes_cuando_hay_mas_de_mil_filas(): void
    {
        $reserva = $this->reserva();
        $filas = [];
        for ($i = 0; $i < 1250; $i++) {
            $filas[] = [
                'restaurante_id' => $this->restaurante->id,
                'reserva_id' => $reserva->id,
                'accion' => 'confirmada',
                'origen' => 'admin',
                'created_at' => '2024-01-01 10:00:00',
            ];
        }
        foreach (array_chunk($filas, 400) as $lote) {
            ReservaEvento::withoutGlobalScopes()->insert($lote);
        }
        $this->eventoDe('2026-10-20 10:00:00');

        $this->artisan('menuonl:purgar-reserva-eventos')->assertSuccessful();

        $this->assertSame(1, ReservaEvento::withoutGlobalScopes()->count());
    }

    public function test_la_purga_esta_programada_sin_solaparse_con_las_demas_tareas(): void
    {
        $eventos = collect(app(Schedule::class)->events());
        $evento = $eventos->first(fn ($e): bool => str_contains((string) $e->command, 'menuonl:purgar-reserva-eventos'));

        $this->assertNotNull($evento, 'menuonl:purgar-reserva-eventos no está programado.');
        $this->assertSame('50 4 * * *', $evento->expression);
        $this->assertTrue($evento->withoutOverlapping);

        $expresiones = $eventos->map(fn ($e): string => $e->expression)->all();
        $this->assertSame(1, count(array_keys($expresiones, $evento->expression, true)), 'Otra tarea comparte la hora.');
    }
}
