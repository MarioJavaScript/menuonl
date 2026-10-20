<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelApiReservasTest extends TestCase
{
    use RefreshDatabase;

    /** Fixed mid-day instant so "today" is the same date in UTC and in the restaurant's time zone. */
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 18:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function miembro(Restaurante $restaurante, string $rol = 'editor'): User
    {
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario, ['rol' => $rol]);

        return $usuario;
    }

    private function url(Restaurante $restaurante, string $ruta = ''): string
    {
        return "/api/v1/panel/{$restaurante->slug}/reservas".$ruta;
    }

    public function test_guest_gets_401(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->getJson($this->url($restaurante))->assertUnauthorized();
    }

    public function test_non_member_gets_404_and_cannot_read_other_restaurant(): void
    {
        $propio = Restaurante::factory()->create();
        $ajeno = Restaurante::factory()->create();
        Reserva::factory()->create(['restaurante_id' => $ajeno->id]);
        $usuario = $this->miembro($propio);

        $this->actingAs($usuario)->getJson($this->url($ajeno))->assertNotFound();
    }

    public function test_inactive_user_and_suspended_restaurant_are_rejected(): void
    {
        $restaurante = Restaurante::factory()->create();
        $inactivo = $this->miembro($restaurante);
        $inactivo->update(['activo' => false]);
        $suspendido = Restaurante::factory()->suspendido()->create();
        $usuario = $this->miembro($suspendido);

        $this->actingAs($inactivo)->getJson($this->url($restaurante))->assertNotFound();
        $this->actingAs($usuario)->getJson($this->url($suspendido))->assertNotFound();
    }

    public function test_lists_only_todays_reservations_of_the_restaurant_ordered_by_time(): void
    {
        $restaurante = Restaurante::factory()->create();
        $otro = Restaurante::factory()->create();
        Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'nombre' => 'Tarde', 'hora' => '21:00:00']);
        Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'nombre' => 'Temprano', 'hora' => '13:00:00']);
        Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'fecha' => now()->addDay()->toDateString()]);
        Reserva::factory()->create(['restaurante_id' => $otro->id]);

        $respuesta = $this->actingAs($this->miembro($restaurante))->getJson($this->url($restaurante));

        $respuesta->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame(['Temprano', 'Tarde'], array_column($respuesta->json('data'), 'nombre'));
        $this->assertSame('13:00', $respuesta->json('data.0.hora'));
    }

    public function test_filters_by_date_and_rejects_bad_date(): void
    {
        $restaurante = Restaurante::factory()->create();
        $manana = now()->addDay()->toDateString();
        Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'fecha' => $manana]);
        $usuario = $this->miembro($restaurante);

        $this->actingAs($usuario)->getJson($this->url($restaurante).'?fecha='.$manana)->assertJsonCount(1, 'data');
        $this->actingAs($usuario)->getJson($this->url($restaurante).'?fecha=nope')->assertUnprocessable();
    }

    public function test_creates_reservation_confirmed_by_default_in_active_restaurant(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->actingAs($this->miembro($restaurante))
            ->postJson($this->url($restaurante), ['nombre' => '  Familia Ortega ', 'personas' => 6, 'hora' => '20:30'])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Familia Ortega')
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.hora', '20:30');

        $this->assertDatabaseHas('reservas', ['restaurante_id' => $restaurante->id, 'personas' => 6]);
    }

    public function test_validation_limits(): void
    {
        $restaurante = Restaurante::factory()->create();
        $usuario = $this->miembro($restaurante);

        $this->actingAs($usuario)->postJson($this->url($restaurante), [])->assertJsonValidationErrors(['nombre', 'personas', 'hora']);
        $this->actingAs($usuario)->postJson($this->url($restaurante), ['nombre' => 'A', 'personas' => 31, 'hora' => '20:00'])->assertJsonValidationErrors('personas');
        $this->actingAs($usuario)->postJson($this->url($restaurante), ['nombre' => 'A', 'personas' => 0, 'hora' => '20:00'])->assertJsonValidationErrors('personas');
        $this->actingAs($usuario)->postJson($this->url($restaurante), ['nombre' => 'A', 'personas' => 2, 'hora' => '25:99'])->assertJsonValidationErrors('hora');
        $this->actingAs($usuario)->postJson($this->url($restaurante), ['nombre' => 'A', 'personas' => 2, 'hora' => '20:00', 'estado' => 'x'])->assertJsonValidationErrors('estado');
    }

    public function test_updates_status_and_moves_time(): void
    {
        $restaurante = Restaurante::factory()->create();
        $reserva = Reserva::factory()->create(['restaurante_id' => $restaurante->id]);
        $usuario = $this->miembro($restaurante, 'propietario');

        $this->actingAs($usuario)->patchJson($this->url($restaurante, "/{$reserva->id}"), ['estado' => 'llego', 'hora' => '19:30'])
            ->assertOk()->assertJsonPath('data.estado', 'llego')->assertJsonPath('data.hora', '19:30');

        $this->assertDatabaseHas('reservas', ['id' => $reserva->id, 'estado' => 'llego']);
    }

    public function test_cannot_touch_reservation_of_another_restaurant(): void
    {
        $propio = Restaurante::factory()->create();
        $ajeno = Restaurante::factory()->create();
        $reserva = Reserva::factory()->create(['restaurante_id' => $ajeno->id, 'estado' => 'pendiente']);
        $usuario = $this->miembro($propio);

        $this->actingAs($usuario)->patchJson($this->url($propio, "/{$reserva->id}"), ['estado' => 'cancelada'])->assertNotFound();
        $this->actingAs($usuario)->deleteJson($this->url($propio, "/{$reserva->id}"))->assertNotFound();
        $this->assertDatabaseHas('reservas', ['id' => $reserva->id, 'estado' => 'pendiente']);
    }

    public function test_superadmin_can_access_any_restaurant_and_delete(): void
    {
        $restaurante = Restaurante::factory()->create();
        $reserva = Reserva::factory()->create(['restaurante_id' => $restaurante->id]);
        $super = User::factory()->superadmin()->create();

        $this->actingAs($super)->deleteJson($this->url($restaurante, "/{$reserva->id}"))->assertNoContent();
        $this->assertDatabaseMissing('reservas', ['id' => $reserva->id]);
    }

    public function test_panel_page_requires_login_and_lists_only_own_active_restaurants(): void
    {
        $propio = Restaurante::factory()->create(['nombre' => 'Mio']);
        $suspendido = Restaurante::factory()->suspendido()->create();
        $ajeno = Restaurante::factory()->create();
        $usuario = $this->miembro($propio);
        $suspendido->usuarios()->attach($usuario, ['rol' => 'editor']);

        $this->get('/panel')->assertRedirect('/admin/login');
        $this->getJson('/api/v1/panel')->assertUnauthorized();
        $this->actingAs($usuario)->get('/panel')->assertOk();
        $this->actingAs($usuario)->getJson('/api/v1/panel')
            ->assertOk()->assertExactJson(['data' => [['slug' => $propio->slug, 'nombre' => 'Mio', 'modulos' => ['inicio', 'reservas', 'menu', 'pedidos']]]]);
        $this->assertNotContains($ajeno->slug, array_column($this->getJson('/api/v1/panel')->json('data'), 'slug'));
    }

    public function test_scope_context_is_cleared_after_each_request(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->actingAs($this->miembro($restaurante))->getJson($this->url($restaurante))->assertOk();

        $this->assertNull(RestauranteScope::getRestauranteId());
    }
}
