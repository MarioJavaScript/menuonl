<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\Horario;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAjustesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function conRol(Restaurante $restaurante, string $rol = 'gerente'): User
    {
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario, ['rol' => $rol]);

        return $usuario;
    }

    private function url(Restaurante $restaurante, string $ruta = ''): string
    {
        return "/api/v1/panel/{$restaurante->slug}/ajustes".$ruta;
    }

    public function test_only_roles_with_the_settings_module_can_read_or_write(): void
    {
        $restaurante = Restaurante::factory()->create();
        $this->getJson($this->url($restaurante))->assertUnauthorized();

        foreach (['editor', 'recepcion', 'cocina'] as $rol) {
            $usuario = $this->conRol($restaurante, $rol);
            $this->actingAs($usuario)->getJson($this->url($restaurante))->assertForbidden();
            $this->actingAs($usuario)->patchJson($this->url($restaurante), ['nombre' => 'Hack'])->assertForbidden();
        }

        $this->assertNotSame('Hack', $restaurante->refresh()->nombre);
    }

    public function test_show_returns_defaults_and_seven_days(): void
    {
        $restaurante = Restaurante::factory()->create();

        $r = $this->actingAs($this->conRol($restaurante))->getJson($this->url($restaurante))->assertOk();

        $r->assertJsonPath('data.plantilla', 'a')->assertJsonPath('data.reservas_activas', true)
            ->assertJsonPath('data.reservas_cupo', 30)->assertJsonPath('data.reservas_franja_min', 60)
            ->assertJsonCount(7, 'data.horarios')->assertJsonCount(3, 'data.plantillas');
        $this->assertTrue($r->json('data.horarios.0.cerrado'));
    }

    public function test_updates_restaurant_data_contacts_and_map(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->actingAs($this->conRol($restaurante))->patchJson($this->url($restaurante), [
            'nombre' => '  Casa Nueva ', 'eslogan' => 'Buen sabor', 'whatsapp' => '+52 55 1234 5678', 'telefono' => '55 1111 2222',
            'direccion' => 'Calle 1', 'instagram' => '@casa', 'latitud' => 19.4326, 'longitud' => -99.1332,
        ])->assertOk()->assertJsonPath('data.nombre', 'Casa Nueva')->assertJsonPath('data.direccion', 'Calle 1')->assertJsonPath('data.latitud', 19.4326);

        $this->assertDatabaseHas('restaurantes', ['id' => $restaurante->id, 'whatsapp_pedidos' => '+52 55 1234 5678', 'eslogan' => 'Buen sabor']);
        $this->assertDatabaseHas('contactos', ['restaurante_id' => $restaurante->id, 'tipo' => 'telefono', 'valor' => '55 1111 2222']);
    }

    public function test_updating_a_contact_edits_the_existing_row_and_clearing_it_removes_it(): void
    {
        $restaurante = Restaurante::factory()->create();
        Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'direccion', 'valor' => 'Vieja']);
        $usuario = $this->conRol($restaurante);

        $this->actingAs($usuario)->patchJson($this->url($restaurante), ['direccion' => 'Nueva'])->assertOk();
        $this->assertSame(1, Contacto::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->where('tipo', 'direccion')->count());
        $this->assertDatabaseHas('contactos', ['tipo' => 'direccion', 'valor' => 'Nueva']);

        $this->actingAs($usuario)->patchJson($this->url($restaurante), ['direccion' => '  '])->assertOk()->assertJsonPath('data.direccion', null);
        $this->assertDatabaseMissing('contactos', ['restaurante_id' => $restaurante->id, 'tipo' => 'direccion']);
    }

    public function test_validation(): void
    {
        $restaurante = Restaurante::factory()->create();
        $usuario = $this->conRol($restaurante);
        $url = $this->url($restaurante);

        $this->actingAs($usuario)->patchJson($url, ['nombre' => ''])->assertJsonValidationErrors('nombre');
        $this->actingAs($usuario)->patchJson($url, ['whatsapp' => 'abc'])->assertJsonValidationErrors('whatsapp');
        $this->actingAs($usuario)->patchJson($url, ['latitud' => 91, 'longitud' => 0])->assertJsonValidationErrors('latitud');
        $this->actingAs($usuario)->patchJson($url, ['latitud' => 10])->assertJsonValidationErrors('longitud');
        $this->actingAs($usuario)->patchJson($url, ['plantilla' => 'z'])->assertJsonValidationErrors('plantilla');
        $this->actingAs($usuario)->patchJson($url, ['reservas_cupo' => 0])->assertJsonValidationErrors('reservas_cupo');
        $this->actingAs($usuario)->patchJson($url, ['reservas_franja_min' => 45])->assertJsonValidationErrors('reservas_franja_min');
    }

    public function test_changes_template_and_reservation_capacity(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->actingAs($this->conRol($restaurante))
            ->patchJson($this->url($restaurante), ['plantilla' => 'c', 'reservas_activas' => false, 'reservas_cupo' => 12, 'reservas_franja_min' => 90])
            ->assertOk()->assertJsonPath('data.plantilla', 'c');

        $this->assertDatabaseHas('restaurantes', ['id' => $restaurante->id, 'sitio_plantilla' => 'c', 'reservas_activas' => 0, 'reservas_cupo' => 12, 'reservas_franja_min' => 90]);
    }

    public function test_sets_a_weekday_shift_and_closes_a_day(): void
    {
        $restaurante = Restaurante::factory()->create();
        $usuario = $this->conRol($restaurante);

        $this->actingAs($usuario)->putJson($this->url($restaurante, '/horarios/2'), ['cerrado' => false, 'abre' => '13:00', 'cierra' => '23:30'])
            ->assertOk()->assertJsonPath('data.2.abre', '13:00')->assertJsonPath('data.2.cerrado', false);
        $this->actingAs($usuario)->putJson($this->url($restaurante, '/horarios/2'), ['cerrado' => false, 'abre' => '14:00', 'cierra' => '22:00'])->assertOk();
        $this->assertSame(1, Horario::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->where('dia_semana', 2)->count());

        $this->actingAs($usuario)->putJson($this->url($restaurante, '/horarios/2'), ['cerrado' => true])->assertOk()->assertJsonPath('data.2.cerrado', true);
        $this->assertDatabaseHas('horarios', ['restaurante_id' => $restaurante->id, 'dia_semana' => 2, 'cerrado' => 1]);
    }

    public function test_hours_validation_and_multi_shift_days_are_protected(): void
    {
        $restaurante = Restaurante::factory()->create();
        Horario::factory()->count(2)->create(['restaurante_id' => $restaurante->id, 'dia_semana' => 5, 'cerrado' => false]);
        $usuario = $this->conRol($restaurante);

        $this->actingAs($usuario)->putJson($this->url($restaurante, '/horarios/1'), ['cerrado' => false])->assertJsonValidationErrors(['abre', 'cierra']);
        $this->actingAs($usuario)->putJson($this->url($restaurante, '/horarios/1'), ['cerrado' => false, 'abre' => '25:00', 'cierra' => '22:00'])->assertJsonValidationErrors('abre');
        $this->actingAs($usuario)->putJson($this->url($restaurante, '/horarios/9'), ['cerrado' => true])->assertNotFound();
        $this->actingAs($usuario)->putJson($this->url($restaurante, '/horarios/5'), ['cerrado' => true])->assertUnprocessable();
        $this->assertSame(2, Horario::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->where('dia_semana', 5)->count());
    }

    public function test_changes_never_leak_to_another_restaurant(): void
    {
        $propio = Restaurante::factory()->create();
        $ajeno = Restaurante::factory()->create(['nombre' => 'Ajeno']);
        Horario::factory()->create(['restaurante_id' => $ajeno->id, 'dia_semana' => 1, 'abre' => '10:00:00', 'cierra' => '20:00:00', 'cerrado' => false]);
        $usuario = $this->conRol($propio);

        $this->actingAs($usuario)->putJson($this->url($propio, '/horarios/1'), ['cerrado' => true])->assertOk();
        $this->actingAs($usuario)->getJson($this->url($ajeno))->assertNotFound();
        $this->actingAs($usuario)->patchJson($this->url($ajeno), ['nombre' => 'X'])->assertNotFound();

        $this->assertDatabaseHas('horarios', ['restaurante_id' => $ajeno->id, 'dia_semana' => 1, 'cerrado' => 0]);
        $this->assertSame('Ajeno', $ajeno->refresh()->nombre);
    }
}
