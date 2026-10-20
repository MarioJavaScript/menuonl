<?php

namespace Tests\Feature;

use App\Mail\InvitacionMail;
use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PanelEquipoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function conRol(Restaurante $restaurante, string $rol): User
    {
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario, ['rol' => $rol]);

        return $usuario;
    }

    private function url(Restaurante $restaurante, string $ruta = ''): string
    {
        return "/api/v1/panel/{$restaurante->slug}/equipo".$ruta;
    }

    public function test_lists_members_and_pending_invitations_of_the_restaurant_only(): void
    {
        $restaurante = Restaurante::factory()->create();
        $otro = Restaurante::factory()->create();
        $gerente = $this->conRol($restaurante, 'gerente');
        $this->conRol($otro, 'cocina');
        Invitacion::factory()->create(['restaurante_id' => $otro->id, 'email' => 'ajeno@example.com']);

        $r = $this->actingAs($gerente)->getJson($this->url($restaurante))->assertOk();

        $r->assertJsonCount(1, 'data.miembros')->assertJsonPath('data.miembros.0.es_yo', true)->assertJsonCount(0, 'data.invitaciones');
        $this->assertNotContains('propietario', $r->json('data.roles_asignables'));
    }

    public function test_gerente_invites_by_email_with_an_operational_role(): void
    {
        Mail::fake();
        $restaurante = Restaurante::factory()->create();
        $gerente = $this->conRol($restaurante, 'gerente');

        $this->actingAs($gerente)->postJson($this->url($restaurante, '/invitaciones'), ['email' => 'Nueva@Example.com', 'rol' => 'recepcion'])
            ->assertCreated()->assertJsonPath('data.email', 'nueva@example.com')->assertJsonPath('data.rol', 'recepcion');

        Mail::assertSent(InvitacionMail::class, fn (InvitacionMail $m): bool => $m->hasTo('nueva@example.com'));
        $this->assertDatabaseHas('invitaciones', ['restaurante_id' => $restaurante->id, 'rol' => 'recepcion']);
    }

    public function test_gerente_cannot_hand_out_propietario_but_propietario_can(): void
    {
        Mail::fake();
        $restaurante = Restaurante::factory()->create();
        $gerente = $this->conRol($restaurante, 'gerente');
        $propietario = $this->conRol($restaurante, 'propietario');

        $this->actingAs($gerente)->postJson($this->url($restaurante, '/invitaciones'), ['email' => 'a@example.com', 'rol' => 'propietario'])->assertJsonValidationErrors('rol');
        $this->actingAs($propietario)->postJson($this->url($restaurante, '/invitaciones'), ['email' => 'a@example.com', 'rol' => 'propietario'])->assertCreated();
    }

    public function test_invitation_validation(): void
    {
        $restaurante = Restaurante::factory()->create();
        $gerente = $this->conRol($restaurante, 'gerente');

        $this->actingAs($gerente)->postJson($this->url($restaurante, '/invitaciones'), ['email' => 'no-es-correo', 'rol' => 'x'])->assertJsonValidationErrors(['email', 'rol']);
    }

    public function test_changes_role_and_deactivates_a_member(): void
    {
        $restaurante = Restaurante::factory()->create();
        $gerente = $this->conRol($restaurante, 'gerente');
        $cocina = $this->conRol($restaurante, 'cocina');

        $this->actingAs($gerente)->patchJson($this->url($restaurante, "/usuarios/{$cocina->id}"), ['rol' => 'recepcion', 'activo' => false])
            ->assertOk()->assertJsonPath('data.rol', 'recepcion')->assertJsonPath('data.activo', false);

        $this->assertDatabaseHas('restaurante_usuario', ['user_id' => $cocina->id, 'rol' => 'recepcion', 'activo' => 0]);
    }

    public function test_cannot_change_own_role_nor_touch_a_propietario_nor_a_stranger(): void
    {
        $restaurante = Restaurante::factory()->create();
        $ajeno = Restaurante::factory()->create();
        $gerente = $this->conRol($restaurante, 'gerente');
        $propietario = $this->conRol($restaurante, 'propietario');
        $forastero = $this->conRol($ajeno, 'cocina');

        $this->actingAs($gerente)->patchJson($this->url($restaurante, "/usuarios/{$gerente->id}"), ['activo' => false])->assertUnprocessable();
        $this->actingAs($gerente)->patchJson($this->url($restaurante, "/usuarios/{$propietario->id}"), ['rol' => 'cocina'])->assertForbidden();
        $this->actingAs($gerente)->patchJson($this->url($restaurante, "/usuarios/{$forastero->id}"), ['rol' => 'cocina'])->assertNotFound();
        $this->assertDatabaseHas('restaurante_usuario', ['user_id' => $propietario->id, 'rol' => 'propietario']);
    }

    public function test_revokes_pending_invitation_of_own_restaurant_only(): void
    {
        $restaurante = Restaurante::factory()->create();
        $ajeno = Restaurante::factory()->create();
        $gerente = $this->conRol($restaurante, 'gerente');
        $propia = Invitacion::factory()->create(['restaurante_id' => $restaurante->id]);
        $otra = Invitacion::factory()->create(['restaurante_id' => $ajeno->id]);

        $this->actingAs($gerente)->deleteJson($this->url($restaurante, "/invitaciones/{$otra->id}"))->assertNoContent();
        $this->assertDatabaseHas('invitaciones', ['id' => $otra->id]);

        $this->actingAs($gerente)->deleteJson($this->url($restaurante, "/invitaciones/{$propia->id}"))->assertNoContent();
        $this->assertDatabaseMissing('invitaciones', ['id' => $propia->id]);
    }
}
