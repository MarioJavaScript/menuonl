<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Services\ConflictoDeReserva;
use App\Services\GestionDeReservas;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Reservation writes of the service panel (GestionDeReservas): business rules, plan and role ceiling, isolation between
 * restaurants and the guarded update. The panel API and the Filament pages use this same service.
 *
 * Time is fixed with Carbon::setTestNow; no ids are written by hand, and every value fits its column (MariaDB strict).
 */
class GestionDeReservasTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private GestionDeReservas $gestion;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 18:00:00', 'UTC'));
        $this->setUpEscenarioDePanel();
        $this->gestion = app(GestionDeReservas::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{0: Restaurante, 1: User}
     */
    private function escenario(string $rol = 'propietario', ?Restaurante $restaurante = null): array
    {
        $restaurante ??= Restaurante::factory()->create();
        RestauranteScope::setRestauranteId($restaurante->id);

        return [$restaurante, $this->crearUsuario($rol, $restaurante)];
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return ['nombre' => 'Ana Prueba', 'personas' => 2, 'hora' => '20:00', ...$cambios];
    }

    public function test_crear_usa_estado_confirmada_hoy_y_origen_panel_por_defecto(): void
    {
        [$restaurante, $usuario] = $this->escenario();

        $reserva = $this->gestion->crear($usuario, $restaurante, $this->datos(['nombre' => '  Ana Prueba  ']))->fresh();

        $this->assertSame('Ana Prueba', $reserva->nombre);
        $this->assertSame('confirmada', $reserva->estado);
        $this->assertSame('2026-10-21', $reserva->fecha->toDateString());
        $this->assertSame('panel', $reserva->origen);
        $this->assertSame($restaurante->id, $reserva->restaurante_id);
    }

    public function test_hoy_sigue_la_zona_horaria_del_restaurante_y_no_la_de_la_aplicacion(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 03:30:00', 'UTC'));

        [$mexico, $usuarioMexico] = $this->escenario(restaurante: Restaurante::factory()->create(['zona_horaria' => 'America/Mexico_City']));
        $this->assertSame('2026-10-21', $this->gestion->crear($usuarioMexico, $mexico, $this->datos())->fecha->toDateString());

        [$auckland, $usuarioAuckland] = $this->escenario(restaurante: Restaurante::factory()->create(['zona_horaria' => 'Pacific/Auckland']));
        $this->assertSame('2026-10-22', $this->gestion->crear($usuarioAuckland, $auckland, $this->datos())->fecha->toDateString());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function datosInvalidos(): array
    {
        return [
            'nombre vacío' => [['nombre' => ''], 'nombre'],
            'nombre de 101 caracteres' => [['nombre' => str_repeat('a', 101)], 'nombre'],
            'cero personas' => [['personas' => 0], 'personas'],
            'treinta y una personas' => [['personas' => 31], 'personas'],
            'personas con texto' => [['personas' => 'dos'], 'personas'],
            'hora sin cero' => [['hora' => '8:00'], 'hora'],
            'hora fuera de rango' => [['hora' => '25:00'], 'hora'],
            'fecha inexistente' => [['fecha' => '2026-02-30'], 'fecha'],
            'fecha con otro formato' => [['fecha' => '21/10/2026'], 'fecha'],
            'estado desconocido' => [['estado' => 'x'], 'estado'],
            'nota de 256 caracteres' => [['nota' => str_repeat('n', 256)], 'nota'],
        ];
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    #[DataProvider('datosInvalidos')]
    public function test_crear_rechaza_datos_invalidos_sin_guardar_nada(array $cambios, string $campo): void
    {
        [$restaurante, $usuario] = $this->escenario();

        try {
            $this->gestion->crear($usuario, $restaurante, $this->datos($cambios));
            $this->fail('Debía rechazar el dato.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors());
        }

        $this->assertSame(0, Reserva::withoutGlobalScopes()->count());
    }

    public function test_crear_acepta_los_limites_de_cada_columna(): void
    {
        [$restaurante, $usuario] = $this->escenario();

        $reserva = $this->gestion->crear($usuario, $restaurante, $this->datos([
            'nombre' => str_repeat('a', 100),
            'personas' => 30,
            'hora' => '23:59',
            'fecha' => '2026-12-31',
            'nota' => str_repeat('n', 255),
            'estado' => 'pendiente',
        ]))->fresh();

        $this->assertSame(100, mb_strlen($reserva->nombre));
        $this->assertSame(30, $reserva->personas);
        $this->assertSame(255, mb_strlen((string) $reserva->nota));
        $this->assertSame('pendiente', $reserva->estado);
    }

    public function test_los_atajos_de_estado_y_la_restauracion_funcionan_desde_cualquier_estado(): void
    {
        [$restaurante, $usuario] = $this->escenario();

        foreach (Reserva::ESTADOS as $desde) {
            $reserva = Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'estado' => $desde]);

            $this->assertSame('confirmada', $this->gestion->confirmar($usuario, $restaurante, $reserva)->estado);
            $this->assertSame('llego', $this->gestion->marcarLlegada($usuario, $restaurante, $reserva)->estado);
            $this->assertSame('cancelada', $this->gestion->cancelar($usuario, $restaurante, $reserva)->estado);
            $this->assertSame('pendiente', $this->gestion->restaurar($usuario, $restaurante, $reserva)->estado);
        }
    }

    public function test_un_estado_desconocido_se_rechaza(): void
    {
        [$restaurante, $usuario] = $this->escenario();
        $reserva = Reserva::factory()->create(['restaurante_id' => $restaurante->id]);

        $this->expectException(ValidationException::class);
        $this->gestion->cambiarEstado($usuario, $restaurante, $reserva, 'archivada');
    }

    public function test_mover_cambia_la_hora_y_la_fecha(): void
    {
        [$restaurante, $usuario] = $this->escenario();
        $reserva = Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'hora' => '13:00:00']);

        $movida = $this->gestion->mover($usuario, $restaurante, $reserva, '19:30', '2026-10-25');

        $this->assertSame('19:30', substr((string) $movida->hora, 0, 5));
        $this->assertSame('2026-10-25', $movida->fecha->toDateString());
    }

    public function test_dos_personas_que_cambian_campos_distintos_no_se_pisan(): void
    {
        [$restaurante, $usuario] = $this->escenario();
        $creada = Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'estado' => 'pendiente', 'hora' => '13:00:00']);
        $primera = Reserva::findOrFail($creada->id);
        $segunda = Reserva::findOrFail($creada->id);

        $this->gestion->marcarLlegada($usuario, $restaurante, $primera);
        $this->gestion->mover($usuario, $restaurante, $segunda, '21:00');

        $final = Reserva::findOrFail($creada->id);
        $this->assertSame('llego', $final->estado);
        $this->assertSame('21:00', substr((string) $final->hora, 0, 5));
    }

    public function test_el_estado_esperado_evita_pisar_un_cambio_de_otra_persona(): void
    {
        [$restaurante, $usuario] = $this->escenario();
        $reserva = Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'estado' => 'pendiente']);
        $vistaPorLaPrimera = Reserva::findOrFail($reserva->id);

        $this->gestion->cancelar($usuario, $restaurante, Reserva::findOrFail($reserva->id), 'pendiente');

        try {
            $this->gestion->confirmar($usuario, $restaurante, $vistaPorLaPrimera, 'pendiente');
            $this->fail('Debía avisar del conflicto.');
        } catch (ConflictoDeReserva) {
            $this->assertSame('cancelada', Reserva::findOrFail($reserva->id)->estado);
        }
    }

    public function test_el_estado_esperado_correcto_permite_el_cambio(): void
    {
        [$restaurante, $usuario] = $this->escenario();
        $reserva = Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'estado' => 'pendiente']);

        $this->assertSame('confirmada', $this->gestion->confirmar($usuario, $restaurante, $reserva, 'pendiente')->estado);
        $this->assertSame('llego', $this->gestion->marcarLlegada($usuario, $restaurante, $reserva->refresh(), 'confirmada')->estado);
    }

    public function test_la_hora_esperada_evita_mover_dos_veces_la_misma_reserva(): void
    {
        [$restaurante, $usuario] = $this->escenario();
        $reserva = Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'hora' => '13:00:00']);
        $vista = Reserva::findOrFail($reserva->id);

        $this->gestion->mover($usuario, $restaurante, Reserva::findOrFail($reserva->id), '14:00');

        $this->expectException(ConflictoDeReserva::class);
        $this->gestion->mover($usuario, $restaurante, $vista, '15:00', null, ['hora' => '13:00:00']);
    }

    public function test_solo_se_pueden_esperar_estado_hora_y_fecha(): void
    {
        [$restaurante, $usuario] = $this->escenario();
        $reserva = Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'estado' => 'pendiente', 'nombre' => 'Original']);

        $this->gestion->actualizar($usuario, $restaurante, $reserva, ['estado' => 'confirmada'], ['estado' => 'pendiente', 'restaurante_id' => 999999, 'nombre' => 'otro']);

        $this->assertSame('confirmada', $reserva->refresh()->estado);
    }

    public function test_eliminar_borra_la_reserva(): void
    {
        [$restaurante, $usuario] = $this->escenario();
        $reserva = Reserva::factory()->create(['restaurante_id' => $restaurante->id]);

        $this->gestion->eliminar($usuario, $restaurante, $reserva);

        $this->assertDatabaseMissing('reservas', ['id' => $reserva->id]);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function rolesEnPlanCompleto(): array
    {
        return [
            'propietario' => ['propietario', true],
            'gerente' => ['gerente', true],
            'editor' => ['editor', true],
            'recepcion' => ['recepcion', true],
        ];
    }

    #[DataProvider('rolesEnPlanCompleto')]
    public function test_cada_rol_puede_o_no_gestionar_reservas(string $rol, bool $permitido): void
    {
        [$restaurante, $usuario] = $this->escenario($rol);

        if (! $permitido) {
            $this->expectException(AuthorizationException::class);
        }

        $reserva = $this->gestion->crear($usuario, $restaurante, $this->datos());

        $this->assertTrue($permitido);
        $this->assertSame('confirmada', $reserva->estado);
    }

    public function test_el_techo_del_plan_se_aplica_a_propietario_y_a_superadmin(): void
    {
        $soloMenu = Restaurante::factory()->soloMenu()->create();
        $sitioMenu = Restaurante::factory()->sitioMenu()->create();

        [, $propietarioSoloMenu] = $this->escenario(restaurante: $soloMenu);
        $superadmin = $this->crearUsuario('superadmin');

        foreach ([$propietarioSoloMenu, $superadmin] as $actor) {
            try {
                $this->gestion->crear($actor, $soloMenu, $this->datos());
                $this->fail('Solo menú no incluye reservas.');
            } catch (AuthorizationException) {
                $this->assertSame(0, Reserva::withoutGlobalScopes()->count());
            }
        }

        [, $propietarioSitioMenu] = $this->escenario(restaurante: $sitioMenu);
        $this->assertSame('confirmada', $this->gestion->crear($propietarioSitioMenu, $sitioMenu, $this->datos())->estado);
    }

    public function test_sin_permiso_se_responde_403_antes_que_error_de_validacion(): void
    {
        [$restaurante] = $this->escenario();
        $ajeno = $this->crearUsuario('propietario', Restaurante::factory()->create());
        $reserva = Reserva::factory()->create(['restaurante_id' => $restaurante->id]);

        foreach ([
            fn () => $this->gestion->crear($ajeno, $restaurante, ['nombre' => '', 'personas' => 99]),
            fn () => $this->gestion->actualizar($ajeno, $restaurante, $reserva, ['estado' => 'x']),
        ] as $intento) {
            try {
                $intento();
                $this->fail('Debía negar el acceso.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_una_reserva_de_otro_restaurante_se_comporta_como_inexistente(): void
    {
        [$propio, $usuario] = $this->escenario();
        $ajeno = Restaurante::factory()->create();
        RestauranteScope::setRestauranteId(null);
        $reservaAjena = Reserva::factory()->create(['restaurante_id' => $ajeno->id, 'estado' => 'pendiente']);
        RestauranteScope::setRestauranteId($propio->id);

        foreach ([
            fn () => $this->gestion->cancelar($usuario, $propio, $reservaAjena),
            fn () => $this->gestion->mover($usuario, $propio, $reservaAjena, '21:00'),
            fn () => $this->gestion->eliminar($usuario, $propio, $reservaAjena),
        ] as $intento) {
            try {
                $intento();
                $this->fail('Debía responder como no encontrada.');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('pendiente', Reserva::withoutGlobalScopes()->findOrFail($reservaAjena->id)->estado);
    }

    public function test_crear_con_un_contexto_de_otro_restaurante_no_guarda_nada(): void
    {
        [$propio, $usuario] = $this->escenario();
        $otro = Restaurante::factory()->create();
        $otro->usuarios()->attach($usuario, ['rol' => 'propietario']);
        RestauranteScope::setRestauranteId($otro->id);

        try {
            $this->gestion->crear($usuario, $propio, $this->datos());
            $this->fail('Debía rechazar el contexto distinto.');
        } catch (AuthorizationException) {
            $this->assertSame(0, Reserva::withoutGlobalScopes()->count());
        }
    }
}
