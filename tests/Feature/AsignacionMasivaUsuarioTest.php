<?php

namespace Tests\Feature;

use App\Mail\InvitacionMail;
use App\Models\User;
use App\Services\InvitacionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

class AsignacionMasivaUsuarioTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
    }

    public function test_es_superadmin_no_se_asigna_con_create(): void
    {
        $usuario = User::create([
            'name' => 'Intruso',
            'email' => 'intruso@ejemplo.com',
            'password' => 'Contrasena-Segura-123',
            'es_superadmin' => true,
        ]);

        $this->assertFalse($usuario->isSuperadmin());
        $this->assertFalse(User::find($usuario->id)->isSuperadmin());
    }

    public function test_es_superadmin_no_se_asigna_con_fill_ni_con_update(): void
    {
        $usuario = (new User)->fill(['name' => 'X', 'es_superadmin' => true]);
        $this->assertFalse($usuario->isSuperadmin());

        $existente = User::factory()->create();
        $existente->fill(['es_superadmin' => true])->save();
        $existente->update(['es_superadmin' => true]);

        $this->assertFalse(User::find($existente->id)->isSuperadmin());
    }

    public function test_la_asignacion_explicita_si_funciona(): void
    {
        $this->assertTrue(User::factory()->superadmin()->create()->isSuperadmin());
        $this->assertTrue(User::factory()->create()->forceFill(['es_superadmin' => true])->isSuperadmin());
        $this->assertFalse(User::factory()->create()->isSuperadmin());
    }

    public function test_el_seeder_demo_crea_al_superadmin_de_forma_explicita(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::where('email', 'superadmin@menuonl.test')->firstOrFail()->isSuperadmin());
        $this->assertSame(1, User::where('es_superadmin', true)->count());
    }

    public function test_aceptar_una_invitacion_con_campos_extra_no_da_privilegios(): void
    {
        Mail::fake();
        $restaurante = $this->crearRestaurante('a');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $invitacion = app(InvitacionService::class)->invitar($restaurante, 'nuevo@ejemplo.com', 'editor', $propietario);

        $token = null;
        Mail::assertSent(InvitacionMail::class, function (InvitacionMail $mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });

        $this->post("/invitaciones/{$token}", [
            'name' => 'Persona Nueva',
            'password' => 'Contrasena-Segura-123',
            'password_confirmation' => 'Contrasena-Segura-123',
            'es_superadmin' => '1',
            'activo' => '0',
            'email' => 'otro@ejemplo.com',
            'rol' => 'propietario',
        ])->assertRedirect();

        $usuario = User::where('email', 'nuevo@ejemplo.com')->firstOrFail();
        $this->assertFalse($usuario->isSuperadmin());
        $this->assertTrue($usuario->activo);
        $this->assertSame('editor', $usuario->rolEn($restaurante));
        $this->assertDatabaseMissing('users', ['email' => 'otro@ejemplo.com']);
        $this->assertNotNull($invitacion->fresh()->aceptada_en);
    }
}
