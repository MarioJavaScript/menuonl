<?php

namespace Tests\Feature;

use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Models\User;
use App\Services\InvitacionService;
use App\Services\PermisosPanel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Roles offered when inviting or changing a role (PermisosPanel::ROLES_OFRECIDOS): only "propietario" for now.
 * The other roles stay in the code and their members keep working; the server refuses to hand them out to anyone new.
 */
class RolesOfrecidosTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
        Mail::fake();
    }

    public function test_el_unico_rol_ofrecido_es_propietario(): void
    {
        $this->assertSame(['propietario'], PermisosPanel::ROLES_OFRECIDOS);
    }

    public function test_los_roles_asignables_y_modificables_dependen_de_quien_gestiona(): void
    {
        $restaurante = Restaurante::factory()->create();
        $todos = ['propietario', 'gerente', 'editor', 'recepcion'];

        $propietario = $this->crearUsuario('propietario', $restaurante);
        $superadmin = $this->crearUsuario('superadmin');
        $gerente = $this->crearUsuario('gerente', $restaurante);
        $editor = $this->crearUsuario('editor', $restaurante);

        foreach ([$propietario, $superadmin] as $actor) {
            $this->assertSame(['propietario'], PermisosPanel::rolesAsignables($actor, $restaurante->id));
            $this->assertSame($todos, PermisosPanel::rolesModificables($actor, $restaurante->id));
        }

        foreach ([$gerente, $editor] as $actor) {
            $this->assertSame([], PermisosPanel::rolesAsignables($actor, $restaurante->id));
            $this->assertSame(['gerente', 'editor', 'recepcion'], PermisosPanel::rolesModificables($actor, $restaurante->id));
        }
    }

    public function test_el_servicio_rechaza_cualquier_rol_no_ofrecido(): void
    {
        $restaurante = Restaurante::factory()->create();
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $superadmin = $this->crearUsuario('superadmin');
        $servicio = app(InvitacionService::class);

        foreach (['gerente', 'editor', 'recepcion', 'cocina', 'superadmin', ''] as $rol) {
            foreach ([$propietario, $superadmin, null] as $invitador) {
                try {
                    $servicio->invitar($restaurante, 'nueva@ejemplo.com', $rol, $invitador);
                    $this->fail("El rol '{$rol}' no debía poder asignarse.");
                } catch (ValidationException $e) {
                    $this->assertArrayHasKey('rol', $e->errors());
                }
            }
        }

        $this->assertDatabaseMissing('invitaciones', ['restaurante_id' => $restaurante->id]);
        Mail::assertNothingSent();
    }

    public function test_el_servicio_acepta_propietario_de_quien_puede_darlo_y_del_sistema(): void
    {
        $restaurante = Restaurante::factory()->create();
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $superadmin = $this->crearUsuario('superadmin');
        $servicio = app(InvitacionService::class);

        foreach ([['uno@ejemplo.com', $propietario], ['dos@ejemplo.com', $superadmin], ['tres@ejemplo.com', null]] as [$correo, $invitador]) {
            $this->assertSame('propietario', $servicio->invitar($restaurante, $correo, 'propietario', $invitador)->rol);
        }
    }

    public function test_el_gerente_no_puede_invitar_porque_no_puede_dar_propietario(): void
    {
        $restaurante = Restaurante::factory()->create();
        $gerente = $this->crearUsuario('gerente', $restaurante);

        $this->expectException(ValidationException::class);
        app(InvitacionService::class)->invitar($restaurante, 'nueva@ejemplo.com', 'propietario', $gerente);
    }

    public function test_quien_no_puede_invitar_sigue_recibiendo_403_antes_que_el_error_del_rol(): void
    {
        $restaurante = Restaurante::factory()->create();
        $editor = $this->crearUsuario('editor', $restaurante);

        $this->expectException(AuthorizationException::class);
        app(InvitacionService::class)->invitar($restaurante, 'nueva@ejemplo.com', 'editor', $editor);
    }

    public function test_una_invitacion_anterior_con_otro_rol_se_sigue_aceptando(): void
    {
        $restaurante = Restaurante::factory()->create();
        $invitacion = Invitacion::factory()->create(['restaurante_id' => $restaurante->id, 'rol' => 'recepcion', 'email' => 'cocinero@ejemplo.com']);
        $usuario = User::factory()->create(['email' => 'cocinero@ejemplo.com']);

        $aceptado = app(InvitacionService::class)->aceptar($invitacion, $usuario);

        $this->assertNotNull($aceptado);
        $this->assertSame('recepcion', $aceptado->rolEn($restaurante));
    }

    public function test_los_miembros_con_otros_roles_conservan_sus_modulos(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->assertSame(['inicio', 'reservas', 'menu'], PermisosPanel::modulosDe($this->crearUsuario('editor', $restaurante), $restaurante));
        $this->assertSame(['inicio', 'reservas'], PermisosPanel::modulosDe($this->crearUsuario('recepcion', $restaurante), $restaurante));
    }

    /**
     * Riesgo pendiente (docs/integracion-dashboard.md, sección 17): la API de equipo no protege al último propietario
     * activo. Filament sí (UserPolicy::revocarAcceso). Esta prueba solo documenta el comportamiento actual.
     */
    public function test_hoy_el_superadmin_puede_desactivar_al_unico_propietario_activo(): void
    {
        $restaurante = Restaurante::factory()->create();
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $superadmin = $this->crearUsuario('superadmin');
        $url = "/api/v1/panel/{$restaurante->slug}/equipo/usuarios/{$propietario->id}";

        $this->actingAs($propietario)->patchJson($url, ['activo' => false])->assertUnprocessable();
        $this->actingAs($superadmin)->patchJson($url, ['activo' => false])->assertOk();

        $this->assertDatabaseHas('restaurante_usuario', ['user_id' => $propietario->id, 'rol' => 'propietario', 'activo' => 0]);
    }
}
