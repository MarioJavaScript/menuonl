<?php

namespace Tests\Feature;

use App\Models\Horario;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ReservasPublicasTest extends TestCase
{
    use RefreshDatabase;

    /** Wednesday 2026-10-21, 10:00 in Mexico City. */
    private const AHORA = '2026-10-21 10:00:00';

    private Restaurante $restaurante;

    /** Validation tests send many requests; only the rate-limit test keeps the throttle counters. */
    private bool $conLimite = false;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse(self::AHORA, 'America/Mexico_City'));
        RateLimiter::clear('reserva:min:127.0.0.1');
        $this->restaurante = Restaurante::factory()->create([
            'slug' => 'mesa-larga', 'nombre' => 'Mesa Larga', 'reservas_cupo' => 10, 'reservas_franja_min' => 60, 'whatsapp_pedidos' => '+52 55 1234 5678',
        ]);
        $this->abre(3, '13:00', '23:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function abre(int $dia, string $abre, string $cierra, ?Restaurante $restaurante = null): void
    {
        Horario::factory()->create(['restaurante_id' => ($restaurante ?? $this->restaurante)->id, 'dia_semana' => $dia, 'abre' => $abre.':00', 'cierra' => $cierra.':00', 'cerrado' => false]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function datos(array $extra = []): array
    {
        return [
            'nombre' => 'Lucía Márquez', 'telefono' => '+52 55 9999 0000', 'personas' => 4, 'fecha' => '2026-10-21', 'hora' => '20:00',
            'nota' => null, 'ts' => now()->timestamp - 10, ...$extra,
        ];
    }

    private function reservar(array $extra = [], string $slug = 'mesa-larga')
    {
        if (! $this->conLimite) {
            Cache::flush();
        }

        return $this->postJson("/api/v1/sitio/{$slug}/reservas", $this->datos($extra));
    }

    public function test_availability_lists_the_slots_of_an_open_day_and_none_for_a_closed_one(): void
    {
        $r = $this->getJson('/api/v1/sitio/mesa-larga/disponibilidad?fecha=2026-10-21')->assertOk();
        $horas = array_column($r->json('data.franjas'), 'hora');

        $this->assertSame(['13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00'], $horas);
        $this->getJson('/api/v1/sitio/mesa-larga/disponibilidad?fecha=2026-10-22')->assertOk()->assertJsonCount(0, 'data.franjas');
    }

    public function test_availability_blocks_past_slots_and_full_ones_and_depends_on_party_size(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 14:40:00', 'America/Mexico_City'));
        Reserva::factory()->confirmada()->create(['restaurante_id' => $this->restaurante->id, 'fecha' => '2026-10-21', 'hora' => '20:30:00', 'personas' => 8]);
        Reserva::factory()->cancelada()->create(['restaurante_id' => $this->restaurante->id, 'fecha' => '2026-10-21', 'hora' => '21:00:00', 'personas' => 10]);

        $mapa = fn (int $personas) => collect($this->getJson("/api/v1/sitio/mesa-larga/disponibilidad?fecha=2026-10-21&personas={$personas}")->json('data.franjas'))->pluck('disponible', 'hora');

        $this->assertFalse($mapa(2)['14:00']);
        $this->assertFalse($mapa(2)['15:00']);
        $this->assertTrue($mapa(2)['16:00']);
        $this->assertTrue($mapa(2)['20:00']);
        $this->assertFalse($mapa(3)['20:00']);
        $this->assertTrue($mapa(2)['21:00']);
    }

    public function test_availability_validation_limits_and_unknown_restaurant(): void
    {
        $this->getJson('/api/v1/sitio/mesa-larga/disponibilidad')->assertUnprocessable();
        $this->getJson('/api/v1/sitio/mesa-larga/disponibilidad?fecha=hoy')->assertUnprocessable();
        $this->getJson('/api/v1/sitio/mesa-larga/disponibilidad?fecha=2026-10-21&personas=99')->assertUnprocessable();
        $this->getJson('/api/v1/sitio/mesa-larga/disponibilidad?fecha=2020-01-01')->assertOk()->assertJsonCount(0, 'data.franjas');
        $this->getJson('/api/v1/sitio/mesa-larga/disponibilidad?fecha=2030-01-01')->assertOk()->assertJsonCount(0, 'data.franjas');
        $this->getJson('/api/v1/sitio/no-existe/disponibilidad?fecha=2026-10-21')->assertNotFound();
    }

    public function test_creates_a_pending_reservation_for_the_restaurant_of_the_slug(): void
    {
        $otro = Restaurante::factory()->create(['slug' => 'otro']);

        $this->reservar(['nota' => '<b>Aniversario</b>'])->assertCreated()
            ->assertJsonPath('data.hora', '20:00')->assertJsonPath('data.personas', 4)
            ->assertJsonStructure(['data' => ['mensaje', 'whatsapp_url']]);

        $this->assertStringStartsWith('https://wa.me/525512345678', $this->reservar(['hora' => '21:00'])->json('data.whatsapp_url'));
        $this->assertDatabaseHas('reservas', [
            'restaurante_id' => $this->restaurante->id, 'nombre' => 'Lucía Márquez', 'telefono' => '+52 55 9999 0000',
            'estado' => 'pendiente', 'origen' => 'sitio', 'nota' => 'Aniversario',
        ]);
        $this->assertDatabaseMissing('reservas', ['restaurante_id' => $otro->id]);
    }

    public function test_the_reservation_shows_up_in_the_reception_panel_and_can_be_confirmed(): void
    {
        $this->reservar()->assertCreated();
        $recepcion = User::factory()->create();
        $this->restaurante->usuarios()->attach($recepcion, ['rol' => 'recepcion']);

        $lista = $this->actingAs($recepcion)->getJson('/api/v1/panel/mesa-larga/reservas')->assertOk()->assertJsonCount(1, 'data');
        $lista->assertJsonPath('data.0.estado', 'pendiente')->assertJsonPath('data.0.origen', 'sitio')->assertJsonPath('data.0.telefono', '+52 55 9999 0000');

        $id = $lista->json('data.0.id');
        $this->actingAs($recepcion)->patchJson("/api/v1/panel/mesa-larga/reservas/{$id}", ['estado' => 'confirmada'])->assertOk()->assertJsonPath('data.estado', 'confirmada');
    }

    public function test_rejects_invalid_guest_data(): void
    {
        $this->reservar(['nombre' => ''])->assertJsonValidationErrors('nombre');
        $this->reservar(['nombre' => '<script>alert(1)</script>'])->assertJsonValidationErrors('nombre');
        $this->reservar(['nombre' => 'A'])->assertJsonValidationErrors('nombre');
        $this->reservar(['telefono' => 'abc'])->assertJsonValidationErrors('telefono');
        $this->reservar(['telefono' => '1234'])->assertJsonValidationErrors('telefono');
        $this->reservar(['personas' => 0])->assertJsonValidationErrors('personas');
        $this->reservar(['personas' => 13])->assertJsonValidationErrors('personas');
        $this->reservar(['hora' => '8pm'])->assertJsonValidationErrors('hora');
        $this->reservar(['nota' => str_repeat('x', 256)])->assertJsonValidationErrors('nota');
        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_rejects_past_dates_far_dates_closed_days_off_grid_times_and_late_slots(): void
    {
        $this->reservar(['fecha' => '2026-10-20'])->assertJsonValidationErrors('fecha');
        $this->reservar(['fecha' => '2027-03-01'])->assertJsonValidationErrors('fecha');
        $this->reservar(['fecha' => '2026-10-22'])->assertJsonValidationErrors('hora');
        $this->reservar(['hora' => '20:15'])->assertJsonValidationErrors('hora');
        $this->reservar(['hora' => '12:00'])->assertJsonValidationErrors('hora');
        $this->reservar(['hora' => '23:00'])->assertJsonValidationErrors('hora');
        Carbon::setTestNow(Carbon::parse('2026-10-21 19:50:00', 'America/Mexico_City'));
        $this->reservar(['hora' => '20:00', 'ts' => now()->timestamp - 10])->assertJsonValidationErrors('hora');
        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_full_slots_are_rejected_and_cancelled_reservations_free_the_seats(): void
    {
        $this->reservar(['personas' => 6])->assertCreated();
        $this->reservar(['personas' => 5, 'nombre' => 'Otra Persona'])->assertJsonValidationErrors('hora');
        $this->reservar(['personas' => 4, 'nombre' => 'Otra Persona'])->assertCreated();
        $this->reservar(['personas' => 1, 'nombre' => 'Ultima Persona'])->assertJsonValidationErrors('hora');

        Reserva::withoutGlobalScopes()->where('nombre', 'Lucía Márquez')->update(['estado' => 'cancelada']);
        $this->reservar(['personas' => 6, 'nombre' => 'Ultima Persona'])->assertCreated();
    }

    public function test_reservations_made_at_the_desk_count_against_the_capacity(): void
    {
        Reserva::factory()->confirmada()->create(['restaurante_id' => $this->restaurante->id, 'fecha' => '2026-10-21', 'hora' => '20:30:00', 'personas' => 9]);

        $this->reservar(['personas' => 2])->assertJsonValidationErrors('hora');
        $this->reservar(['personas' => 1])->assertCreated();
    }

    public function test_capacity_of_another_restaurant_does_not_interfere(): void
    {
        $otro = Restaurante::factory()->create(['slug' => 'otro', 'reservas_cupo' => 10]);
        $this->abre(3, '13:00', '23:00', $otro);
        Reserva::factory()->confirmada()->create(['restaurante_id' => $otro->id, 'fecha' => '2026-10-21', 'hora' => '20:00:00', 'personas' => 10]);

        $this->reservar(['personas' => 10])->assertCreated();
        $this->reservar(['personas' => 1], 'otro')->assertJsonValidationErrors('hora');
    }

    public function test_paused_suspended_and_unknown_restaurants(): void
    {
        $this->restaurante->update(['reservas_activas' => false]);
        $this->reservar()->assertJsonValidationErrors('fecha');
        $this->getJson('/api/v1/sitio/mesa-larga/disponibilidad?fecha=2026-10-21')->assertJsonCount(0, 'data.franjas');

        $this->restaurante->update(['estado' => 'suspendido']);
        $this->reservar()->assertNotFound();
        $this->reservar([], 'no-existe')->assertNotFound();
        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_honeypot_looks_successful_but_stores_nothing(): void
    {
        $this->reservar(['sitio_web' => 'http://spam.example'])->assertCreated();

        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_the_form_timestamp_must_be_present_recent_enough_and_not_too_old(): void
    {
        $this->reservar(['ts' => now()->timestamp])->assertUnprocessable();
        $this->reservar(['ts' => now()->timestamp - 50000])->assertUnprocessable();
        $this->postJson('/api/v1/sitio/mesa-larga/reservas', collect($this->datos())->except('ts')->all())->assertJsonValidationErrors('ts');
        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_rate_limit_stops_bursts(): void
    {
        $this->conLimite = true;

        foreach (range(1, 3) as $n) {
            $this->reservar(['hora' => '13:00', 'personas' => 1, 'nombre' => 'Persona '.['Uno', 'Dos', 'Tres'][$n - 1]])->assertCreated();
        }

        $this->reservar(['hora' => '14:00'])->assertStatus(429);
    }

    public function test_scope_context_is_cleared_after_public_requests(): void
    {
        $this->reservar()->assertCreated();
        $this->getJson('/api/v1/sitio/mesa-larga/disponibilidad?fecha=2026-10-21')->assertOk();

        $this->assertNull(RestauranteScope::getRestauranteId());
    }

    public function test_the_public_site_renders_the_form_only_when_reservations_are_active(): void
    {
        $this->get('/mesa-larga')->assertOk()->assertSee('id="form-reserva"', false)->assertSee('name="sitio_web"', false)
            ->assertSee('/api/v1/sitio/mesa-larga/reservas', false)->assertSee('wa.me/525512345678', false);

        $this->restaurante->update(['reservas_activas' => false]);
        $this->get('/mesa-larga')->assertOk()->assertDontSee('id="form-reserva"', false)->assertSee('no recibimos reservas en línea');
    }
}
