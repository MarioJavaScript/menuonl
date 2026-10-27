<?php

namespace Tests\Feature;

use App\Filament\Pages\Reservas;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Lo que la página de Reservas pinta de verdad en el navegador: Blade no compila directivas (@js, @json...) dentro de
 * los atributos de un componente <x-...>, así que se lee el HTML renderizado y no solo las acciones de Livewire.
 */
class ReservasHtmlTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const NOMBRE_DIFICIL = 'O\'Brien "x" <b>Ana</b> & Co';

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

    private function reserva(string $estado, string $nombre = 'Ana'): Reserva
    {
        return Reserva::factory()->create([
            'restaurante_id' => $this->restaurante->id,
            'fecha' => '2026-10-21',
            'hora' => '20:00:00',
            'estado' => $estado,
            'nombre' => $nombre,
        ]);
    }

    /**
     * Cada wire:click="mountAction(...)" del HTML, ya decodificado como lo hace el navegador.
     *
     * @return list<string>
     */
    private function expresiones(string $html): array
    {
        preg_match_all('/wire:click="(mountAction\([^"]*)"/', $html, $coincidencias);

        return array_map(fn (string $atributo): string => html_entity_decode($atributo, ENT_QUOTES | ENT_HTML5), $coincidencias[1]);
    }

    /**
     * @return array<string, int> acción => id de reserva, de los botones por fila
     */
    private function botonesPorFila(string $html): array
    {
        $botones = [];
        foreach ($this->expresiones($html) as $expresion) {
            if (preg_match('/^mountAction\(\'([a-z_]+)\', \{ reserva: (\d+) \}\)$/', $expresion, $partes) === 1) {
                $botones[$partes[1]] = (int) $partes[2];
            }
        }

        return $botones;
    }

    public function test_el_html_no_trae_directivas_ni_codigo_que_el_analizador_de_wire_click_no_soporte(): void
    {
        $this->reserva('pendiente', self::NOMBRE_DIFICIL);

        $html = Livewire::test(Reservas::class)->html();

        foreach (['@js(', '@json(', '@php', '@if (', '@foreach', '@class(', 'atob(', 'JSON.parse('] as $texto) {
            $this->assertStringNotContainsString($texto, $html, "No debe aparecer en el HTML: {$texto}");
        }
    }

    public function test_cada_wire_click_de_mount_action_trae_solo_el_id_de_la_reserva(): void
    {
        $reserva = $this->reserva('pendiente', self::NOMBRE_DIFICIL);
        $html = Livewire::test(Reservas::class)->html();

        $expresiones = $this->expresiones($html);
        $this->assertNotEmpty($expresiones);

        foreach ($expresiones as $expresion) {
            if ($expresion === "mountAction('crear')") {
                continue;
            }
            $this->assertMatchesRegularExpression('/^mountAction\(\'[a-z_]+\', \{ reserva: \d+ \}\)$/', $expresion);
        }

        $botones = $this->botonesPorFila($html);
        foreach (['confirmar', 'mover', 'cancelar', 'historial'] as $accion) {
            $this->assertSame($reserva->id, $botones[$accion] ?? null, "Falta el botón {$accion}");
        }
    }

    public function test_el_nombre_con_comillas_apostrofo_y_etiquetas_no_rompe_el_html(): void
    {
        $this->reserva('confirmada', self::NOMBRE_DIFICIL);
        $html = Livewire::test(Reservas::class)->html();

        $this->assertStringNotContainsString('<b>Ana</b>', $html);

        $documento = new \DOMDocument;
        libxml_use_internal_errors(true);
        $documento->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
        libxml_clear_errors();

        $encontrados = 0;
        foreach ($documento->getElementsByTagName('button') as $boton) {
            $clic = $boton->getAttribute('wire:click');
            if (str_starts_with($clic, "mountAction('") && $clic !== "mountAction('crear')") {
                $encontrados++;
                $this->assertMatchesRegularExpression('/^mountAction\(\'[a-z_]+\', \{ reserva: \d+ \}\)$/', $clic);
                $this->assertStringContainsString('O\'Brien', $boton->getAttribute('aria-label'));
            }
        }
        $this->assertGreaterThanOrEqual(4, $encontrados);
    }

    public function test_los_botones_cambian_segun_el_estado(): void
    {
        $this->reserva('cancelada');
        $this->reserva('llego');
        $acciones = array_keys($this->botonesPorFila(Livewire::test(Reservas::class)->html()));

        $this->assertContains('restaurar', $acciones);
        $this->assertContains('historial', $acciones);
        $this->assertNotContains('cancelar', $acciones);
    }

    public function test_ejecutar_con_livewire_la_misma_accion_y_argumento_que_trae_el_html(): void
    {
        $reserva = $this->reserva('pendiente', self::NOMBRE_DIFICIL);
        $botones = $this->botonesPorFila(Livewire::test(Reservas::class)->html());

        foreach (['mover', 'cancelar', 'historial'] as $accion) {
            Livewire::test(Reservas::class)
                ->call('mountAction', $accion, ['reserva' => $botones[$accion]])
                ->assertActionMounted($accion);
        }

        Livewire::test(Reservas::class)
            ->call('mountAction', 'mover', ['reserva' => $botones['mover']])
            ->assertActionDataSet(['fecha' => '2026-10-21', 'hora' => '20:00']);

        Livewire::test(Reservas::class)
            ->mountAction('cancelar', ['reserva' => $botones['cancelar']])
            ->callMountedAction()
            ->assertNotified('Reserva cancelada');
        $this->assertSame('cancelada', $reserva->fresh()->estado);
    }

    public function test_confirmar_con_el_argumento_del_html_escribe_el_cambio(): void
    {
        $reserva = $this->reserva('pendiente', self::NOMBRE_DIFICIL);
        $botones = $this->botonesPorFila(Livewire::test(Reservas::class)->html());

        Livewire::test(Reservas::class)->callAction('confirmar', arguments: ['reserva' => $botones['confirmar']]);

        $this->assertSame('confirmada', $reserva->fresh()->estado);
    }

    public function test_ninguna_vista_deja_una_directiva_blade_dentro_de_un_atributo_wire(): void
    {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'), RecursiveDirectoryIterator::SKIP_DOTS));
        $infracciones = [];

        foreach ($it as $archivo) {
            if (! str_ends_with((string) $archivo, '.blade.php')) {
                continue;
            }
            $texto = (string) file_get_contents((string) $archivo);
            preg_match_all('/\swire:[\w.:-]+="([^"]*)"/', $texto, $atributos);
            foreach ($atributos[1] as $valor) {
                if (preg_match('/(?<![\w@])@(js|json|php|if|elseif|else|unless|foreach|for|class|lang|can|isset|empty)\b/', $valor) === 1) {
                    $infracciones[] = str_replace('\\', '/', substr((string) $archivo, strlen(resource_path()) + 1)).': '.$valor;
                }
            }
        }

        $this->assertSame([], $infracciones, "Directivas Blade sin compilar dentro de atributos wire:*:\n".implode("\n", $infracciones));
    }
}
