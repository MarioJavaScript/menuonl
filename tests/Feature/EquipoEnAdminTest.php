<?php

namespace Tests\Feature;

use App\Filament\Resources\Invitaciones\Pages\CreateInvitacion;
use App\Filament\Resources\Invitaciones\Pages\ListInvitaciones;
use App\Models\Invitacion;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Equipo en /admin tras retirar el /panel viejo: "Quitar acceso" (UserPolicy::revocarAcceso, ver PoliticasMatrizTest y
 * AislamientoPanelTest) más invitar y revocar invitaciones. Desactivar, reactivar y cambiar rol ya no existen: se quita el
 * acceso y se vuelve a invitar.
 */
class EquipoEnAdminTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    public function test_el_propietario_revoca_una_invitacion_pendiente_de_su_restaurante_y_la_de_otro_ni_se_ve(): void
    {
        $restaurante = $this->crearRestaurante('local-uno');
        $ajeno = $this->crearRestaurante('local-dos');
        $propia = Invitacion::factory()->create(['restaurante_id' => $restaurante->id]);
        $otra = Invitacion::factory()->create(['restaurante_id' => $ajeno->id]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(ListInvitaciones::class)
            ->assertCanSeeTableRecords([$propia])
            ->assertCanNotSeeTableRecords([$otra])
            ->callAction(TestAction::make('delete')->table($propia));

        $this->assertDatabaseMissing('invitaciones', ['id' => $propia->id]);
        $this->assertDatabaseHas('invitaciones', ['id' => $otra->id]);
    }

    public function test_el_editor_no_abre_invitaciones(): void
    {
        $restaurante = $this->crearRestaurante('local-uno');
        $this->entrarAlPanel($this->crearUsuario('editor', $restaurante), $restaurante);

        Livewire::test(ListInvitaciones::class)->assertForbidden();
        Livewire::test(CreateInvitacion::class)->assertForbidden();
    }
}
