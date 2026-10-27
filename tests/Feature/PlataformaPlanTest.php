<?php

namespace Tests\Feature;

use App\Enums\Capacidad;
use App\Enums\PlanRestaurante;
use App\Filament\Resources\Restaurantes\Actions\CambiarPlanAction;
use App\Filament\Resources\Restaurantes\Pages\CreateRestaurante;
use App\Filament\Resources\Restaurantes\Pages\EditRestaurante;
use App\Filament\Resources\Restaurantes\Pages\ListRestaurantes;
use App\Models\HistorialCambio;
use App\Models\Reserva;
use App\Models\Restaurante;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Selector de plan en Plataforma (docs/planes.md, paso 5): solo el superadmin lo ve y lo cambia, con confirmación,
 * historial y sin valores viejos en el siguiente request.
 */
class PlataformaPlanTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00:00', 'America/Mexico_City'));
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function planesDelEnum(): array
    {
        return [
            'completo' => ['completo'],
            'sitio y menú' => ['sitio_menu'],
            'solo menú' => ['solo_menu'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function planesFueraDelEnum(): array
    {
        return [
            'inventado' => ['premium'],
            'vacío' => [''],
            'mayúsculas' => ['COMPLETO'],
            'con espacios' => [' completo '],
        ];
    }

    private function entrarComoSuperadmin(?Restaurante $restaurante = null): Restaurante
    {
        $restaurante ??= Restaurante::factory()->create();
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $restaurante);

        return $restaurante;
    }

    public function test_la_politica_solo_deja_cambiar_el_plan_al_superadmin(): void
    {
        $restaurante = Restaurante::factory()->create();
        $ajeno = Restaurante::factory()->create();

        $this->assertTrue($this->crearUsuario('superadmin')->can('cambiarPlan', $restaurante));

        foreach (['propietario', 'editor', 'gerente', 'recepcion'] as $rol) {
            $this->assertFalse($this->crearUsuario($rol, $restaurante)->can('cambiarPlan', $restaurante), $rol);
        }

        $this->assertFalse($this->crearUsuario('propietario', $ajeno)->can('cambiarPlan', $restaurante));
    }

    public function test_el_modelo_rechaza_el_cambio_de_quien_no_es_superadmin_aunque_llegue_por_otra_via(): void
    {
        $restaurante = Restaurante::factory()->create();
        $propietario = $this->crearUsuario('propietario', $restaurante);

        try {
            $restaurante->cambiarPlan(PlanRestaurante::SoloMenu, $propietario);
            $this->fail('El propietario no debe poder cambiar el plan.');
        } catch (AuthorizationException) {
            // Esperado.
        }

        $this->assertSame(PlanRestaurante::Completo, $restaurante->fresh()->plan);
        $this->assertSame(0, HistorialCambio::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->count());
    }

    public function test_el_cambio_deja_en_el_historial_quien_cuando_y_los_planes(): void
    {
        $restaurante = Restaurante::factory()->create();
        $superadmin = $this->crearUsuario('superadmin');

        $this->assertTrue($restaurante->cambiarPlan(PlanRestaurante::SitioMenu, $superadmin));

        $this->assertSame(PlanRestaurante::SitioMenu, $restaurante->fresh()->plan);

        $registros = HistorialCambio::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->get();
        $this->assertCount(1, $registros);

        $registro = $registros->first();
        $this->assertSame($superadmin->id, $registro->usuario_id);
        $this->assertSame('restaurante', $registro->entidad);
        $this->assertSame($restaurante->id, $registro->entidad_id);
        $this->assertSame('actualizar', $registro->accion);
        $this->assertSame(['plan' => ['antes' => 'completo', 'despues' => 'sitio_menu']], $registro->cambios);
        $this->assertTrue($registro->created_at->equalTo(Carbon::now()));
    }

    public function test_un_cambio_al_mismo_plan_no_escribe_nada(): void
    {
        $restaurante = Restaurante::factory()->sitioMenu()->create();

        $this->assertFalse($restaurante->cambiarPlan(PlanRestaurante::SitioMenu, $this->crearUsuario('superadmin')));

        $this->assertSame(0, HistorialCambio::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->count());
    }

    public function test_cada_cambio_queda_en_el_historial_en_orden(): void
    {
        $restaurante = Restaurante::factory()->create();
        $superadmin = $this->crearUsuario('superadmin');

        $restaurante->cambiarPlan(PlanRestaurante::SoloMenu, $superadmin);
        Carbon::setTestNow(Carbon::now()->addMinute());
        $restaurante->cambiarPlan(PlanRestaurante::Completo, $superadmin);

        $cambios = HistorialCambio::withoutGlobalScopes()
            ->where('restaurante_id', $restaurante->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (HistorialCambio $registro): array => $registro->cambios['plan'])
            ->all();

        $this->assertSame([
            ['antes' => 'completo', 'despues' => 'solo_menu'],
            ['antes' => 'solo_menu', 'despues' => 'completo'],
        ], $cambios);
    }

    #[DataProvider('planesDelEnum')]
    public function test_la_accion_cambia_el_plan_desde_la_lista_de_plataforma(string $plan): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();
        $this->entrarComoSuperadmin();

        Livewire::test(ListRestaurantes::class)
            ->callAction(TestAction::make('cambiarPlan')->table($restaurante), ['plan' => $plan])
            ->assertHasNoFormErrors();

        $this->assertSame(PlanRestaurante::from($plan), $restaurante->fresh()->plan);
    }

    public function test_la_accion_tambien_esta_en_la_pantalla_de_edicion(): void
    {
        $restaurante = Restaurante::factory()->create();
        $this->entrarComoSuperadmin();

        Livewire::test(EditRestaurante::class, ['record' => $restaurante->getRouteKey()])
            ->callAction('cambiarPlan', ['plan' => 'solo_menu'])
            ->assertHasNoFormErrors();

        $this->assertSame(PlanRestaurante::SoloMenu, $restaurante->fresh()->plan);
    }

    #[DataProvider('planesFueraDelEnum')]
    public function test_la_accion_rechaza_cualquier_valor_fuera_de_los_tres_planes(string $plan): void
    {
        $restaurante = Restaurante::factory()->create();
        $this->entrarComoSuperadmin();

        Livewire::test(ListRestaurantes::class)
            ->callAction(TestAction::make('cambiarPlan')->table($restaurante), ['plan' => $plan])
            ->assertHasFormErrors(['plan']);

        $this->assertSame(PlanRestaurante::Completo, $restaurante->fresh()->plan);
        $this->assertSame(0, HistorialCambio::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->count());
    }

    public function test_quien_no_es_superadmin_no_llega_a_plataforma_ni_a_la_edicion(): void
    {
        $restaurante = Restaurante::factory()->create();

        foreach (['propietario', 'editor'] as $rol) {
            $usuario = $this->crearUsuario($rol, $restaurante);

            $this->actingAs($usuario)->get("/admin/{$restaurante->slug}/restaurantes")->assertForbidden();
            $this->actingAs($usuario)->get("/admin/{$restaurante->slug}/restaurantes/{$restaurante->id}/edit")->assertForbidden();
        }
    }

    public function test_la_accion_esta_oculta_para_quien_no_es_superadmin(): void
    {
        $restaurante = Restaurante::factory()->create();
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $this->assertFalse(auth()->user()->can('cambiarPlan', $restaurante));

        $vista = Livewire::test(ListRestaurantes::class);
        $vista->assertForbidden();
    }

    public function test_la_ventana_explica_que_pierde_el_restaurante_al_pasar_a_solo_menu(): void
    {
        $restaurante = Restaurante::factory()->create();

        $texto = CambiarPlanAction::explicacion($restaurante, PlanRestaurante::SoloMenu)->toHtml();

        foreach (['panel', 'sitio', 'editar_menu', 'qr', 'equipo'] as $capacidad) {
            $this->assertStringContainsString(e(__("panel.planes.capacidades.{$capacidad}.pierde")), $texto, $capacidad);
        }
        $this->assertStringContainsString(e(__('panel.planes.cambiar.pierde')), $texto);
        $this->assertStringNotContainsString(e(__('panel.planes.cambiar.gana')), $texto);
        $this->assertStringContainsString(e(__('panel.planes.cambiar.sigue')), $texto);
        $this->assertStringContainsString(e(__('panel.planes.cambiar.superadmin')), $texto);
    }

    public function test_la_ventana_explica_que_gana_el_restaurante_al_subir_de_plan(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();

        $texto = CambiarPlanAction::explicacion($restaurante, PlanRestaurante::Completo)->toHtml();

        $this->assertStringContainsString(e(__('panel.planes.capacidades.sitio.gana')), $texto);
        $this->assertStringContainsString(e(__('panel.planes.capacidades.panel.gana')), $texto);
        $this->assertStringNotContainsString(e(__('panel.planes.cambiar.pierde')), $texto);
    }

    public function test_la_ventana_pide_elegir_otro_plan_si_no_hay_cambio(): void
    {
        $restaurante = Restaurante::factory()->sitioMenu()->create();

        foreach ([null, PlanRestaurante::SitioMenu] as $nuevo) {
            $texto = CambiarPlanAction::explicacion($restaurante, $nuevo)->toHtml();

            $this->assertStringContainsString(e(__('panel.planes.cambiar.sin_cambio')), $texto);
            $this->assertStringContainsString(e(PlanRestaurante::SitioMenu->getLabel()), $texto);
            $this->assertStringNotContainsString(e(__('panel.planes.cambiar.sigue')), $texto);
        }
    }

    public function test_la_ventana_avisa_de_las_reservas_por_confirmar_solo_si_se_cierran_las_reservas(): void
    {
        $restaurante = Restaurante::factory()->create();
        $otro = Restaurante::factory()->create();
        Reserva::factory()->count(2)->create(['restaurante_id' => $restaurante->id, 'estado' => 'pendiente']);
        Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'estado' => 'confirmada']);
        Reserva::factory()->create(['restaurante_id' => $otro->id, 'estado' => 'pendiente']);

        $this->assertStringContainsString('Hay 2 reservas por confirmar', CambiarPlanAction::explicacion($restaurante, PlanRestaurante::SoloMenu)->toHtml());
        $this->assertStringNotContainsString('por confirmar', CambiarPlanAction::explicacion($restaurante, PlanRestaurante::SitioMenu)->toHtml());
    }

    public function test_el_modal_de_la_lista_se_abre_y_responde_a_la_eleccion_del_plan(): void
    {
        $restaurante = Restaurante::factory()->create();
        $this->entrarComoSuperadmin();

        Livewire::test(ListRestaurantes::class)
            ->mountAction(TestAction::make('cambiarPlan')->table($restaurante))
            ->assertActionMounted(TestAction::make('cambiarPlan')->table($restaurante))
            ->assertSchemaStateSet(['plan' => PlanRestaurante::Completo])
            ->fillForm(['plan' => 'solo_menu'])
            ->assertSchemaStateSet(['plan' => PlanRestaurante::SoloMenu]);

        $this->assertSame(PlanRestaurante::Completo, $restaurante->fresh()->plan);
    }

    public function test_el_cambio_de_plan_no_borra_reservas_equipo_ni_plantilla(): void
    {
        $restaurante = Restaurante::factory()->create(['sitio_plantilla' => 'f']);
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $reserva = Reserva::factory()->create(['restaurante_id' => $restaurante->id]);

        $restaurante->cambiarPlan(PlanRestaurante::SoloMenu, $this->crearUsuario('superadmin'));

        $this->assertDatabaseHas('reservas', ['id' => $reserva->id]);
        $this->assertSame('propietario', $propietario->fresh()->rolEn($restaurante->id));
        $this->assertSame('f', $restaurante->fresh()->sitio_plantilla);
    }

    public function test_el_cambio_vacia_la_cache_del_restaurante(): void
    {
        $restaurante = Restaurante::factory()->create();
        Cache::put("restaurante:id:{$restaurante->id}", 'viejo', 3600);
        Cache::put("restaurante:slug:{$restaurante->slug}", 'viejo', 3600);
        Cache::put("menu:{$restaurante->id}:es", ['viejo'], 3600);

        $restaurante->cambiarPlan(PlanRestaurante::SoloMenu, $this->crearUsuario('superadmin'));

        $this->assertNull(Cache::get("restaurante:id:{$restaurante->id}"));
        $this->assertNull(Cache::get("restaurante:slug:{$restaurante->slug}"));
        $this->assertNull(Cache::get("menu:{$restaurante->id}:es"));
    }

    public function test_lo_que_ve_el_propietario_cambia_en_su_siguiente_peticion(): void
    {
        $restaurante = Restaurante::factory()->create();
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $superadmin = $this->crearUsuario('superadmin');
        $base = "/api/v1/panel/{$restaurante->slug}";

        $this->get(route('sitio.publico', ['slug' => $restaurante->slug]))->assertOk();
        $this->actingAs($propietario)->getJson($base.'/ajustes')->assertOk();

        $restaurante->cambiarPlan(PlanRestaurante::SoloMenu, $superadmin);

        $this->get(route('sitio.publico', ['slug' => $restaurante->slug]))
            ->assertRedirect(route('menu.publico', ['slug' => $restaurante->slug]));
        $this->actingAs($propietario)->getJson($base.'/ajustes')->assertNotFound();
        $this->actingAs($propietario)->get("/admin/{$restaurante->slug}")->assertForbidden();

        $restaurante->cambiarPlan(PlanRestaurante::Completo, $superadmin);

        $this->get(route('sitio.publico', ['slug' => $restaurante->slug]))->assertOk();
        $this->actingAs($propietario)->getJson($base.'/ajustes')->assertOk();
    }

    public function test_el_plan_de_un_restaurante_no_afecta_a_otro(): void
    {
        $uno = Restaurante::factory()->create();
        $otro = Restaurante::factory()->create();

        $uno->cambiarPlan(PlanRestaurante::SoloMenu, $this->crearUsuario('superadmin'));

        $this->assertSame(PlanRestaurante::SoloMenu, $uno->fresh()->plan);
        $this->assertSame(PlanRestaurante::Completo, $otro->fresh()->plan);
    }

    public function test_la_lista_muestra_la_columna_del_plan_y_filtra_por_plan(): void
    {
        $completo = Restaurante::factory()->create();
        $sitioMenu = Restaurante::factory()->sitioMenu()->create();
        $soloMenu = Restaurante::factory()->soloMenu()->create();
        $this->entrarComoSuperadmin();

        Livewire::test(ListRestaurantes::class)
            ->assertTableColumnExists('plan')
            ->assertSee(PlanRestaurante::SitioMenu->getLabel())
            ->assertSee(PlanRestaurante::SoloMenu->getLabel())
            ->filterTable('plan', 'solo_menu')
            ->assertCanSeeTableRecords([$soloMenu])
            ->assertCanNotSeeTableRecords([$completo, $sitioMenu])
            ->filterTable('plan', 'sitio_menu')
            ->assertCanSeeTableRecords([$sitioMenu])
            ->assertCanNotSeeTableRecords([$completo, $soloMenu]);
    }

    public function test_al_crear_un_restaurante_el_plan_por_defecto_es_completo(): void
    {
        $this->entrarComoSuperadmin();

        Livewire::test(CreateRestaurante::class)
            ->assertFormSet(['plan' => PlanRestaurante::Completo])
            ->fillForm($this->datosDeAlta('con-plan-por-defecto'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(PlanRestaurante::Completo, Restaurante::where('slug', 'con-plan-por-defecto')->firstOrFail()->plan);
    }

    #[DataProvider('planesDelEnum')]
    public function test_al_crear_un_restaurante_se_puede_elegir_el_plan(string $plan): void
    {
        $this->entrarComoSuperadmin();

        Livewire::test(CreateRestaurante::class)
            ->fillForm($this->datosDeAlta('alta-'.str_replace('_', '-', $plan)) + ['plan' => $plan])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(PlanRestaurante::from($plan), Restaurante::where('slug', 'alta-'.str_replace('_', '-', $plan))->firstOrFail()->plan);
    }

    #[DataProvider('planesFueraDelEnum')]
    public function test_al_crear_un_restaurante_se_rechaza_un_plan_fuera_del_enum(string $plan): void
    {
        $this->entrarComoSuperadmin();

        Livewire::test(CreateRestaurante::class)
            ->fillForm($this->datosDeAlta('alta-rechazada') + ['plan' => $plan])
            ->call('create')
            ->assertHasFormErrors(['plan']);

        $this->assertDatabaseMissing('restaurantes', ['slug' => 'alta-rechazada']);
    }

    public function test_el_formulario_de_edicion_no_ofrece_el_plan_ni_lo_cambia(): void
    {
        $restaurante = Restaurante::factory()->create();
        $this->entrarComoSuperadmin();

        Livewire::test(EditRestaurante::class, ['record' => $restaurante->getRouteKey()])
            ->assertFormFieldDoesNotExist('plan')
            ->fillForm(['nombre' => 'Nombre nuevo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nombre nuevo', $restaurante->fresh()->nombre);
        $this->assertSame(PlanRestaurante::Completo, $restaurante->fresh()->plan);
    }

    public function test_el_plan_no_se_asigna_en_masa_ni_se_guarda_un_valor_fuera_del_enum(): void
    {
        $restaurante = Restaurante::factory()->create();

        $restaurante->update(['plan' => 'solo_menu', 'nombre' => 'Otro nombre']);
        $this->assertSame(PlanRestaurante::Completo, $restaurante->fresh()->plan);

        $this->expectException(\ValueError::class);
        $restaurante->forceFill(['plan' => 'premium'])->save();
    }

    public function test_hay_texto_de_ganancia_y_perdida_para_cada_capacidad_salvo_metricas(): void
    {
        foreach (Capacidad::cases() as $capacidad) {
            if ($capacidad === Capacidad::Metricas) {
                continue;
            }

            foreach (['gana', 'pierde'] as $sentido) {
                $clave = "panel.planes.capacidades.{$capacidad->value}.{$sentido}";
                $this->assertNotSame($clave, __($clave), $clave);
            }
        }
    }

    public function test_los_cambios_entre_planes_salen_de_la_tabla_de_capacidades(): void
    {
        $perdidas = PlanRestaurante::Completo->cambiosHacia(PlanRestaurante::SoloMenu);

        $this->assertSame([], $perdidas['gana']);
        $this->assertContains(__('panel.planes.capacidades.panel.pierde'), $perdidas['pierde']);
        $this->assertContains(__('panel.planes.capacidades.sitio.pierde'), $perdidas['pierde']);

        $intermedio = PlanRestaurante::Completo->cambiosHacia(PlanRestaurante::SitioMenu);
        $this->assertContains(__('panel.planes.capacidades.editar_menu.pierde'), $intermedio['pierde']);
        $this->assertNotContains(__('panel.planes.capacidades.sitio.pierde'), $intermedio['pierde']);

        $this->assertSame(['gana' => [], 'pierde' => []], PlanRestaurante::SitioMenu->cambiosHacia(PlanRestaurante::SitioMenu));

        $ida = PlanRestaurante::SitioMenu->cambiosHacia(PlanRestaurante::SoloMenu);
        $vuelta = PlanRestaurante::SoloMenu->cambiosHacia(PlanRestaurante::SitioMenu);
        $this->assertCount(count($ida['pierde']), $vuelta['gana']);
    }

    /**
     * @return array<string, string>
     */
    private function datosDeAlta(string $slug): array
    {
        return [
            'nombre' => 'Restaurante '.$slug,
            'slug' => $slug,
            'moneda' => 'MXN',
            'idioma_default' => 'es',
            'zona_horaria' => 'America/Mexico_City',
            'estado' => 'activo',
            'email_propietario' => $slug.'@ejemplo.com',
        ];
    }
}
