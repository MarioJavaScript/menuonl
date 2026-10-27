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

    private function documento(string $html): \DOMDocument
    {
        $documento = new \DOMDocument;
        libxml_use_internal_errors(true);
        $documento->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
        libxml_clear_errors();

        return $documento;
    }

    /**
     * Valores de los atributos de eventos que escribe nuestra vista. Se ignoran wire:snapshot, wire:effects y los
     * <script>, que traen JSON.parse(...) de Livewire y de Filament.
     *
     * @return list<string>
     */
    private function valoresDeAtributosDeEventos(string $html): array
    {
        $valores = [];
        foreach ((new \DOMXPath($this->documento($html)))->query('//*[not(self::script)]/@*') as $atributo) {
            $nombre = $atributo->nodeName;
            if (preg_match('/^(wire:(click|submit|keydown)(\.|$)|x-on:|@click)/', $nombre) === 1) {
                $valores[] = $atributo->nodeValue;
            }
        }

        return $valores;
    }

    /**
     * El HTML visible: sin <script> ni los atributos wire:snapshot y wire:effects.
     */
    private function htmlVisible(string $html): string
    {
        $documento = $this->documento($html);
        $xpath = new \DOMXPath($documento);

        foreach (iterator_to_array($xpath->query('//script')) as $script) {
            $script->parentNode->removeChild($script);
        }
        foreach ($xpath->query('//*[@*[name()="wire:snapshot" or name()="wire:effects"]]') as $elemento) {
            $elemento->removeAttribute('wire:snapshot');
            $elemento->removeAttribute('wire:effects');
        }

        return (string) $documento->saveHTML($documento->getElementsByTagName('body')->item(0));
    }

    /**
     * @param  list<string>  $valores
     * @return list<string>
     */
    private function codigoProhibidoEn(array $valores): array
    {
        $encontrados = [];
        foreach ($valores as $valor) {
            foreach (['atob(', 'JSON.parse(', '@js(', '@json('] as $texto) {
                if (str_contains($valor, $texto)) {
                    $encontrados[] = $texto;
                }
            }
        }

        return $encontrados;
    }

    /**
     * @return list<string>
     */
    private function directivasSinCompilarEn(string $htmlVisible): array
    {
        return array_values(array_filter(
            ['@js(', '@json(', '@php', '@if (', '@foreach', '@class('],
            fn (string $texto): bool => str_contains($htmlVisible, $texto),
        ));
    }

    public function test_los_atributos_de_eventos_no_traen_codigo_que_el_analizador_de_wire_click_no_soporte(): void
    {
        $this->reserva('pendiente', self::NOMBRE_DIFICIL);
        $html = Livewire::test(Reservas::class)->html();

        $valores = $this->valoresDeAtributosDeEventos($html);
        $this->assertNotEmpty($valores);
        $this->assertSame([], $this->codigoProhibidoEn($valores));
    }

    public function test_el_html_visible_no_trae_directivas_blade_sin_compilar(): void
    {
        $this->reserva('pendiente', self::NOMBRE_DIFICIL);
        $html = Livewire::test(Reservas::class)->html();

        $this->assertSame([], $this->directivasSinCompilarEn($this->htmlVisible($html)));
    }

    public function test_de_control_el_detector_falla_con_codigo_prohibido_y_no_con_los_scripts_de_livewire(): void
    {
        $malo = '<div wire:effects="{}"><script>window.x = JSON.parse(\'{}\')</script>'
            .'<button wire:click="mountAction(\'cancelar\', JSON.parse(atob(\'e30=\')))">a</button>'
            .'<button wire:click="mountAction(\'mover\', @js($argumentos))">b</button>'
            .'<p>@php @if ($x)</p></div>';

        $this->assertSame(['atob(', 'JSON.parse(', '@js('], array_values(array_unique(array_merge(...array_map(
            fn (string $valor): array => $this->codigoProhibidoEn([$valor]),
            $this->valoresDeAtributosDeEventos($malo),
        )))));
        $this->assertSame(['@js(', '@php', '@if ('], $this->directivasSinCompilarEn($this->htmlVisible($malo)));

        $bueno = '<div wire:snapshot="{&quot;a&quot;:&quot;JSON.parse(&quot;}" wire:effects="JSON.parse(1)"><script>JSON.parse(\'{}\')</script>'
            .'<button wire:click="mountAction(\'cancelar\', { reserva: 7 })">a</button></div>';

        $this->assertSame([], $this->codigoProhibidoEn($this->valoresDeAtributosDeEventos($bueno)));
        $this->assertSame([], $this->directivasSinCompilarEn($this->htmlVisible($bueno)));
        $this->assertCount(1, $this->valoresDeAtributosDeEventos($bueno));
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
