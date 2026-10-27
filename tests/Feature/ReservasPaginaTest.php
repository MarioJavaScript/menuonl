<?php

namespace Tests\Feature;

use App\Enums\PlanRestaurante;
use App\Filament\Pages\Reservas;
use App\Models\Reserva;
use App\Models\ReservaEvento;
use App\Models\Restaurante;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Página Reservas de /admin (docs/integracion-dashboard.md, sección 18): permisos por rol y plan, pestañas, cada acción,
 * aislamiento entre restaurantes, argumentos manipulados desde el navegador, conflictos y sondeo.
 *
 * El tiempo se fija con Carbon::setTestNow (UTC 18:00 es mediodía en Ciudad de México, sin horario de verano); no se
 * escriben ids a mano y los valores caben en sus columnas (MariaDB estricto).
 */
class ReservasPaginaTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private Restaurante $restaurante;

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 18:00:00', 'UTC'));
        $this->setUpEscenarioDePanel();

        $this->restaurante = $this->crearRestaurante('la-esquina');
        $this->propietario = $this->crearUsuario('propietario', $this->restaurante);
        $this->entrarAlPanel($this->propietario, $this->restaurante);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function reserva(array $atributos = [], ?Restaurante $restaurante = null): Reserva
    {
        return Reserva::factory()->create($atributos + [
            'restaurante_id' => ($restaurante ?? $this->restaurante)->id,
            'fecha' => '2026-10-21',
            'hora' => '20:00:00',
        ]);
    }

    /**
     * Lo único que la pantalla manda en cada botón: el id de la reserva.
     *
     * @return array{reserva: int}
     */
    private function argumentos(Reserva $reserva): array
    {
        return ['reserva' => $reserva->id];
    }

    private function ajenaConReserva(): Reserva
    {
        // Sin restaurante activo para poder escribir en otro (el contexto del panel lo impediría).
        Filament::setTenant(null);
        $ajena = $this->reserva(['nombre' => 'Persona Ajena'], $this->crearRestaurante('otro-lugar'));
        Filament::setTenant($this->restaurante);

        return $ajena;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sinCambios(Reserva $reserva): array
    {
        $reserva = $reserva->fresh();

        return ['estado' => $reserva->estado, 'hora' => (string) $reserva->hora, 'fecha' => $reserva->fecha->toDateString(), 'nombre' => $reserva->nombre];
    }

    // ---------------------------------------------------------------- acceso por rol y plan

    public function test_el_propietario_abre_la_pagina_y_ve_sus_reservas_pero_no_las_de_otro_restaurante(): void
    {
        $this->reserva(['nombre' => 'Familia Rojas']);
        $this->ajenaConReserva();

        $this->get(Reservas::getUrl())
            ->assertOk()
            ->assertSee('Familia Rojas')
            ->assertDontSee('Persona Ajena')
            ->assertSee('Próximas');
    }

    public function test_sitio_y_menu_ve_la_pagina_y_solo_menu_no(): void
    {
        $sitioMenu = Restaurante::factory()->sitioMenu()->create();
        $this->entrarAlPanel($this->crearUsuario('propietario', $sitioMenu), $sitioMenu);
        $this->assertTrue(Reservas::canAccess());

        $soloMenu = Restaurante::factory()->soloMenu()->create();
        $this->entrarAlPanel($this->crearUsuario('propietario', $soloMenu), $soloMenu);
        $this->assertFalse(Reservas::canAccess());
    }

    public function test_solo_menu_recibe_404_al_pedir_la_pagina(): void
    {
        $soloMenu = Restaurante::factory()->soloMenu()->create();
        $this->entrarAlPanel($this->crearUsuario('propietario', $soloMenu), $soloMenu);

        $this->get(Reservas::getUrl())->assertForbidden();
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function rolesYAcceso(): array
    {
        return [
            'propietario' => ['propietario', true],
            'gerente' => ['gerente', true],
            'editor' => ['editor', true],
            'recepcion' => ['recepcion', true],
        ];
    }

    #[DataProvider('rolesYAcceso')]
    public function test_cada_rol_entra_segun_su_modulo_de_reservas(string $rol, bool $entra): void
    {
        $this->entrarAlPanel($this->crearUsuario($rol, $this->restaurante), $this->restaurante);

        $this->assertSame($entra, Reservas::canAccess());
    }

    public function test_el_superadmin_entra_si_el_plan_incluye_reservas_y_no_si_es_solo_menu(): void
    {
        $superadmin = $this->crearUsuario('superadmin');
        $this->entrarAlPanel($superadmin, $this->restaurante);
        $this->assertTrue(Reservas::canAccess());

        $soloMenu = Restaurante::factory()->soloMenu()->create();
        $this->entrarAlPanel($superadmin, $soloMenu);
        $this->assertFalse(Reservas::canAccess());
    }

    public function test_aparece_en_la_navegacion_con_la_insignia_de_las_por_confirmar(): void
    {
        $this->reserva(['estado' => 'pendiente']);
        $this->reserva(['estado' => 'pendiente', 'fecha' => '2026-10-22']);
        $this->reserva(['estado' => 'confirmada']);
        $this->reserva(['estado' => 'pendiente', 'fecha' => '2026-10-20']);

        $this->assertSame('Reservas', Reservas::getNavigationLabel());
        $this->assertSame('Pedidos y reservas', Reservas::getNavigationGroup());
        $this->assertSame('2', Reservas::getNavigationBadge());
        $this->assertSame('2 reservas por confirmar', Reservas::getNavigationBadgeTooltip());
    }

    public function test_sin_reservas_por_confirmar_no_hay_insignia(): void
    {
        $this->reserva(['estado' => 'confirmada']);

        $this->assertNull(Reservas::getNavigationBadge());
    }

    // ---------------------------------------------------------------- pestañas

    public function test_proximas_agrupa_por_dia_y_deja_fuera_lo_pasado(): void
    {
        $this->reserva(['nombre' => 'Ayer', 'fecha' => '2026-10-20']);
        $this->reserva(['nombre' => 'Segunda', 'hora' => '21:00:00']);
        $this->reserva(['nombre' => 'Primera', 'hora' => '13:00:00']);
        $this->reserva(['nombre' => 'Mañana', 'fecha' => '2026-10-22']);

        Livewire::test(Reservas::class)
            ->assertSeeInOrder(['Hoy · mié. 21 oct.', 'Primera', 'Segunda', 'Jueves 22 de octubre', 'Mañana'])
            ->assertDontSee('Ayer')
            ->assertViewHas('grupos', fn ($grupos): bool => $grupos->keys()->all() === ['2026-10-21', '2026-10-22']);
    }

    public function test_hoy_muestra_solo_el_dia_de_hoy_en_la_zona_del_restaurante(): void
    {
        $nuevaZelanda = Restaurante::factory()->create(['zona_horaria' => 'Pacific/Auckland']);
        $this->entrarAlPanel($this->crearUsuario('propietario', $nuevaZelanda), $nuevaZelanda);
        // UTC 18:00 del 21 es 07:00 del 22 en Auckland.
        $this->reserva(['nombre' => 'Hora local', 'fecha' => '2026-10-22'], $nuevaZelanda);
        $this->reserva(['nombre' => 'Un día antes', 'fecha' => '2026-10-21'], $nuevaZelanda);

        Livewire::test(Reservas::class)
            ->call('cambiarVista', 'hoy')
            ->assertSee('Hora local')
            ->assertDontSee('Un día antes');
    }

    public function test_otro_dia_muestra_el_dia_elegido(): void
    {
        $this->reserva(['nombre' => 'De hoy']);
        $this->reserva(['nombre' => 'Del jueves', 'fecha' => '2026-10-22']);

        Livewire::test(Reservas::class)
            ->set('fecha', '2026-10-22')
            ->assertSet('vista', 'fecha')
            ->assertSee('Del jueves')
            ->assertDontSee('De hoy');
    }

    public function test_una_vista_o_fecha_inventada_vuelve_a_proximas_o_pide_elegir_dia(): void
    {
        $this->reserva(['nombre' => 'De hoy']);

        Livewire::test(Reservas::class)
            ->set('vista', 'cualquier-cosa')
            ->assertSee('De hoy');

        Livewire::test(Reservas::class)
            ->set('vista', 'fecha')
            ->assertSee('Elige un día')
            ->assertDontSee('De hoy');

        Livewire::test(Reservas::class)
            ->set('fecha', '2026-02-31')
            ->assertSet('vista', 'proximas')
            ->assertSee('De hoy');
    }

    public function test_proximas_tiene_tope_de_doscientas(): void
    {
        $filas = [];
        for ($i = 0; $i < 205; $i++) {
            $filas[] = [
                'restaurante_id' => $this->restaurante->id,
                'nombre' => 'Mesa '.$i,
                'personas' => 2,
                'fecha' => Carbon::parse('2026-10-21')->addDays($i % 30)->toDateString(),
                'hora' => '20:00:00',
                'estado' => 'pendiente',
                'origen' => 'panel',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Reserva::withoutGlobalScopes()->insert($filas);

        Livewire::test(Reservas::class)
            ->assertViewHas('grupos', fn ($grupos): bool => $grupos->flatten(1)->count() === 200);
    }

    public function test_sin_reservas_muestra_el_estado_vacio_con_el_boton_de_agregar(): void
    {
        Livewire::test(Reservas::class)
            ->assertSee('No hay reservas próximas')
            ->assertSee('Agregar reserva');
    }

    public function test_los_textos_salen_de_lang_y_no_quedan_claves_sin_traducir(): void
    {
        $this->reserva(['origen' => 'sitio']);
        $this->reserva(['estado' => 'cancelada', 'hora' => '22:00:00']);

        Livewire::test(Reservas::class)
            ->assertSee('Desde el sitio')
            ->assertSee('Por confirmar')
            ->assertSee('Cancelada')
            ->assertDontSee('panel.reservas');
    }

    // ---------------------------------------------------------------- acciones

    public function test_confirmar_llego_y_restaurar_cambian_el_estado_y_registran_quien(): void
    {
        $reserva = $this->reserva(['estado' => 'pendiente']);
        $pagina = Livewire::test(Reservas::class);

        $pagina->callAction('confirmar', arguments: $this->argumentos($reserva))->assertNotified('Reserva confirmada');
        $this->assertSame('confirmada', $reserva->fresh()->estado);

        $pagina->callAction('llego', arguments: $this->argumentos($reserva))->assertNotified('Marcada como llegada');
        $this->assertSame('llego', $reserva->fresh()->estado);

        $reserva->forceFill(['estado' => 'cancelada'])->save();
        $pagina->callAction('restaurar', arguments: $this->argumentos($reserva))->assertNotified('Reserva restaurada');
        $this->assertSame('pendiente', $reserva->fresh()->estado);

        $eventos = ReservaEvento::where('reserva_id', $reserva->id)->orderBy('id')->get();
        $this->assertSame(['confirmada', 'llego', 'restaurada'], $eventos->pluck('accion')->all());
        $this->assertSame([$this->propietario->id], $eventos->pluck('usuario_id')->unique()->values()->all());
        $this->assertSame(['admin'], $eventos->pluck('origen')->unique()->values()->all());
    }

    public function test_cancelar_pide_confirmacion_y_cancela(): void
    {
        $reserva = $this->reserva(['estado' => 'confirmada', 'nombre' => 'Familia Rojas']);

        $pagina = Livewire::test(Reservas::class)->mountAction('cancelar', $this->argumentos($reserva));

        $this->assertSame(
            'Vas a cancelar la reserva de Familia Rojas hoy a las 20:00. Puedes restaurarla después.',
            (string) $pagina->instance()->getMountedAction()->getModalDescription(),
        );

        $pagina->callMountedAction()->assertNotified('Reserva cancelada');

        $this->assertSame('cancelada', $reserva->fresh()->estado);
    }

    public function test_agregar_crea_la_reserva_confirmada_con_origen_admin(): void
    {
        Livewire::test(Reservas::class)
            ->callAction('crear', ['nombre' => ' Familia Soto ', 'personas' => 4, 'fecha' => '2026-10-23', 'hora' => '21:30'])
            ->assertNotified('Reserva agregada');

        $reserva = Reserva::where('restaurante_id', $this->restaurante->id)->firstOrFail();
        $this->assertSame('Familia Soto', $reserva->nombre);
        $this->assertSame('confirmada', $reserva->estado);
        $this->assertSame('2026-10-23', $reserva->fecha->toDateString());
        $this->assertSame('creada', ReservaEvento::where('reserva_id', $reserva->id)->value('accion'));
    }

    public function test_agregar_con_datos_invalidos_muestra_el_error_en_el_campo_en_espanol_y_no_guarda(): void
    {
        $pagina = Livewire::test(Reservas::class)
            ->callAction('crear', ['nombre' => '', 'personas' => 99, 'fecha' => 'ayer', 'hora' => '25:99'])
            ->assertHasActionErrors(['nombre', 'personas', 'fecha', 'hora']);

        $this->assertSame(0, Reserva::where('restaurante_id', $this->restaurante->id)->count());
        $this->assertStringNotContainsString('validation.', json_encode($pagina->errors()->toArray(), JSON_UNESCAPED_UNICODE));
    }

    public function test_mover_cambia_hora_y_fecha_y_lo_registra(): void
    {
        $reserva = $this->reserva(['estado' => 'confirmada']);

        Livewire::test(Reservas::class)
            ->callAction('mover', ['hora' => '21:30', 'fecha' => '2026-10-24'], $this->argumentos($reserva))
            ->assertNotified('Reserva movida');

        $reserva->refresh();
        $this->assertSame('2026-10-24', $reserva->fecha->toDateString());
        $this->assertSame('21:30', substr((string) $reserva->hora, 0, 5));

        $evento = ReservaEvento::where('reserva_id', $reserva->id)->firstOrFail();
        $this->assertSame('movida', $evento->accion);
        $this->assertSame('20:00', $evento->hora_antes);
        $this->assertSame('21:30', $evento->hora_despues);
    }

    public function test_el_historial_muestra_quien_hizo_cada_cambio(): void
    {
        $reserva = $this->reserva(['estado' => 'pendiente']);
        $pagina = Livewire::test(Reservas::class);
        $pagina->callAction('confirmar', arguments: $this->argumentos($reserva));

        $pagina->mountAction('historial', $this->argumentos($reserva))
            ->assertSee('Confirmada por '.$this->propietario->name);
    }

    // ---------------------------------------------------------------- argumentos manipulados

    public function test_el_id_de_una_reserva_de_otro_restaurante_no_se_toca(): void
    {
        $ajena = $this->ajenaConReserva();
        $antes = $this->sinCambios($ajena);
        $pagina = Livewire::test(Reservas::class);

        foreach (['confirmar', 'llego', 'restaurar', 'cancelar'] as $accion) {
            $pagina->callAction($accion, arguments: $this->argumentos($ajena))->assertNotified('Esa reserva ya no existe');
        }
        $pagina->callAction('mover', ['hora' => '23:00', 'fecha' => '2026-12-01'], $this->argumentos($ajena))->assertNotified('Esa reserva ya no existe');

        $this->assertSame($antes, $this->sinCambios($ajena));
        $this->assertSame(0, ReservaEvento::withoutGlobalScopes()->count());
    }

    public function test_un_id_inventado_o_que_no_es_numero_no_escribe_nada(): void
    {
        $reserva = $this->reserva();
        $antes = $this->sinCambios($reserva);
        $pagina = Livewire::test(Reservas::class);

        foreach ([null, 'abc', 0, $reserva->id + 9999, ['x']] as $id) {
            $pagina->callAction('confirmar', arguments: ['reserva' => $id])->assertNotified('Esa reserva ya no existe');
        }

        $this->assertSame($antes, $this->sinCambios($reserva));
        $this->assertSame(0, ReservaEvento::withoutGlobalScopes()->count());
    }

    public function test_estado_hora_y_fecha_mandados_por_el_navegador_se_ignoran(): void
    {
        $reserva = $this->reserva(['estado' => 'pendiente']);
        $pagina = Livewire::test(Reservas::class);

        // Un estado inventado no provoca un conflicto ni cambia lo que se escribe: el servidor lee el estado de la base.
        $pagina->callAction('confirmar', arguments: [...$this->argumentos($reserva), 'estado' => 'archivada', 'restaurante_id' => 999999])
            ->assertNotified('Reserva confirmada');
        $this->assertSame('confirmada', $reserva->fresh()->estado);

        // Lo que se manda al abrir la ventana no cuenta como "lo que la persona vio": el servidor lo sobrescribe.
        $pagina->mountAction('mover', [...$this->argumentos($reserva), 'hora_vista' => '03:00:00', 'fecha_vista' => '1999-01-01', 'estado' => 'llego'])
            ->setActionData(['hora' => '21:00', 'fecha' => '2026-10-22'])
            ->callMountedAction()
            ->assertNotified('Reserva movida');
        $this->assertSame('2026-10-22', $reserva->fresh()->fecha->toDateString());
    }

    public function test_mover_con_hora_o_fecha_invalida_muestra_el_error_y_no_escribe(): void
    {
        $reserva = $this->reserva(['estado' => 'confirmada']);
        $antes = $this->sinCambios($reserva);
        $argumentos = $this->argumentos($reserva);

        // Cada intento en una página nueva: con errores la ventana queda abierta.
        Livewire::test(Reservas::class)->callAction('mover', ['hora' => '25:61', 'fecha' => '2026-10-24'], $argumentos)->assertHasActionErrors(['hora']);
        Livewire::test(Reservas::class)->callAction('mover', ['hora' => '21:00', 'fecha' => '2026-13-45'], $argumentos)->assertHasActionErrors(['fecha']);
        Livewire::test(Reservas::class)->callAction('mover', ['hora' => 'DROP TABLE', 'fecha' => "21/10/2026'; --"], $argumentos)->assertHasActionErrors(['hora', 'fecha']);

        $this->assertSame($antes, $this->sinCambios($reserva));
        $this->assertSame(0, ReservaEvento::withoutGlobalScopes()->count());
    }

    public function test_un_rol_sin_el_modulo_recibe_403_y_no_escribe(): void
    {
        $reserva = $this->reserva(['estado' => 'pendiente']);
        $argumentos = $this->argumentos($reserva);
        $pagina = Livewire::test(Reservas::class);

        // La sesión abierta pierde el acceso: se desactiva la membresía antes de la acción.
        $this->propietario->restaurantes()->updateExistingPivot($this->restaurante->id, ['activo' => false]);
        // Cada petición real carga al usuario de nuevo; aquí se simula con una instancia recargada.
        $this->actingAs($this->propietario->fresh());

        $pagina->call('mountAction', 'confirmar', $argumentos)->assertForbidden();

        $this->assertSame('pendiente', $reserva->fresh()->estado);
        $this->assertSame(0, ReservaEvento::withoutGlobalScopes()->count());
    }

    public function test_si_el_plan_baja_a_solo_menu_con_la_pagina_abierta_nada_se_escribe(): void
    {
        $reserva = $this->reserva(['estado' => 'pendiente']);
        $argumentos = $this->argumentos($reserva);
        $pagina = Livewire::test(Reservas::class);

        $this->restaurante->forceFill(['plan' => PlanRestaurante::SoloMenu])->save();
        Filament::setTenant($this->restaurante->fresh());

        $pagina->call('mountAction', 'confirmar', $argumentos)->assertForbidden();
        $this->assertSame('pendiente', $reserva->fresh()->estado);
        $this->assertSame(0, ReservaEvento::withoutGlobalScopes()->count());
    }

    // ---------------------------------------------------------------- conflictos

    public function test_un_cambio_ajeno_antes_del_clic_se_aplica_sobre_el_estado_actual_leido_del_servidor(): void
    {
        $reserva = $this->reserva(['estado' => 'pendiente']);
        $argumentos = $this->argumentos($reserva);
        $pagina = Livewire::test(Reservas::class);

        // Otra persona la cancela después de que esta pantalla la mostró como pendiente. El navegador ya no manda el estado
        // que vio, así que no hay conflicto: la acción parte del estado real (la lista se refresca con el siguiente sondeo).
        $reserva->forceFill(['estado' => 'cancelada'])->save();

        $pagina->callAction('confirmar', arguments: $argumentos)->assertNotified('Reserva confirmada');

        $this->assertSame('confirmada', $reserva->fresh()->estado);
        $this->assertSame(['confirmada'], ReservaEvento::withoutGlobalScopes()->pluck('accion')->all());
    }

    public function test_mover_con_conflicto_deja_la_ventana_abierta_con_lo_elegido_y_los_valores_actuales(): void
    {
        $reserva = $this->reserva(['estado' => 'confirmada']);
        $argumentos = $this->argumentos($reserva);
        $pagina = Livewire::test(Reservas::class);

        $pagina->mountAction('mover', $argumentos);

        // Otra persona la mueve a las 22:00 con la ventana abierta y antes de que se guarde.
        $reserva->forceFill(['hora' => '22:00:00'])->save();

        $pagina
            ->setActionData(['hora' => '19:00', 'fecha' => '2026-10-21'])
            ->callMountedAction()
            ->assertNotified('Otra persona ya cambió esta reserva')
            ->assertActionMounted('mover')
            ->assertActionDataSet(['hora' => '19:00', 'fecha' => '2026-10-21']);

        $this->assertSame('22:00', substr((string) $reserva->fresh()->hora, 0, 5));
        $this->assertSame(0, ReservaEvento::withoutGlobalScopes()->count());

        // Con los valores esperados ya actualizados, el siguiente Guardar es una decisión consciente.
        $pagina->callMountedAction()->assertNotified('Reserva movida');
        $this->assertSame('19:00', substr((string) $reserva->fresh()->hora, 0, 5));
    }

    // ---------------------------------------------------------------- sondeo

    public function test_el_sondeo_esta_en_la_pagina_y_no_pinta_nada_con_una_ventana_abierta(): void
    {
        $pagina = Livewire::test(Reservas::class)->assertSeeHtml('wire:poll.30s.visible="refrescar"');

        $pagina->mountAction('crear');
        $this->reserva(['nombre' => 'Llegó Mientras Escribía']);
        $pagina->call('refrescar')->assertDontSee('Llegó Mientras Escribía');

        $pagina->unmountAction();
        $pagina->call('refrescar')->assertSee('Llegó Mientras Escribía');
    }

    public function test_el_sondeo_anuncia_solo_las_reservas_nuevas_por_confirmar(): void
    {
        $this->reserva(['estado' => 'pendiente']);
        $pagina = Livewire::test(Reservas::class);
        $this->assertSame('', $pagina->get('anuncio'));

        $pagina->call('refrescar')->assertSet('anuncio', '');

        $this->reserva(['estado' => 'pendiente', 'hora' => '21:00:00']);
        $pagina->call('refrescar')->assertSet('anuncio', 'Hay 1 reserva nueva por confirmar.');

        $pagina->call('refrescar')->assertSet('anuncio', '');
    }

    public function test_el_sondeo_trae_los_cambios_de_otra_persona(): void
    {
        $pagina = Livewire::test(Reservas::class)->assertDontSee('Llegó Tarde');
        $this->reserva(['nombre' => 'Llegó Tarde']);

        $pagina->call('refrescar')->assertSee('Llegó Tarde');
    }

    public function test_el_sondeo_de_quien_perdio_el_acceso_responde_403(): void
    {
        $pagina = Livewire::test(Reservas::class);
        $this->restaurante->forceFill(['plan' => PlanRestaurante::SoloMenu])->save();
        Filament::setTenant($this->restaurante->fresh());

        $pagina->call('refrescar')->assertForbidden();
    }

    public function test_la_pagina_incluye_el_script_de_errores_con_el_nonce_de_la_csp(): void
    {
        $respuesta = $this->get(Reservas::getUrl())->assertOk();

        $html = $respuesta->getContent();
        $this->assertStringContainsString('mbSondeoInterceptor', $html);
        $this->assertStringContainsString('Livewire.interceptRequest', $html);

        $csp = (string) $respuesta->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/'nonce-([A-Za-z0-9]+)'/", $csp);
        preg_match("/'nonce-([A-Za-z0-9]+)'/", $csp, $coincidencia);
        $this->assertStringContainsString('nonce="'.$coincidencia[1].'"', substr($html, (int) strrpos(substr($html, 0, (int) strpos($html, 'mbSondeoInterceptor')), '<script'), 400));
    }
}
