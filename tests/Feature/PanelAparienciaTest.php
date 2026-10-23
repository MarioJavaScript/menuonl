<?php

namespace Tests\Feature;

use App\Filament\Pages\Apariencia;
use App\Models\Restaurante;
use App\Services\AparienciaSitio;
use App\Services\Catalogos;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Apariencia vista por el propietario del restaurante (no por el superadmin): primero el diseño y después solo las
 * opciones que le aplican.
 */
class PanelAparienciaTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    public function test_el_propietario_abre_apariencia_y_ve_el_enlace_en_su_menu(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $this->get(Apariencia::getUrl())
            ->assertOk()
            ->assertSee('Apariencia de tu sitio y tu menú')
            ->assertSee('Mi menú')
            ->assertSee('Información')
            ->assertSee('Ver mi sitio')
            ->assertSee('Ver mi menú');
    }

    public function test_cada_diseno_se_muestra_con_imagen_de_muestra_nombre_claro_y_para_quien_es(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $pantalla = Livewire::test(Apariencia::class);

        foreach (AparienciaSitio::fichas() as $ficha) {
            $pantalla->assertSee($ficha['nombre'])->assertSee($ficha['descripcion']);
        }

        $html = $this->get(Apariencia::getUrl())->getContent();
        $this->assertSame(count(Catalogos::plantillasSitio()), substr_count($html, '<svg viewBox="0 0 160 200"'));
        $this->assertStringContainsString('Ideal para: cafeterías y bebidas', $html);
    }

    public function test_el_dueno_no_ve_jerga_ni_la_palabra_carta(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        preg_match('#<main.*?</main>#s', $this->get(Apariencia::getUrl())->getContent(), $principal);
        $texto = strip_tags($principal[0] ?? '');
        $this->assertNotSame('', $texto);

        foreach (['slug', 'plantilla_sitio', 'sitio_plantilla', 'carta'] as $palabra) {
            $this->assertStringNotContainsString($palabra, $texto, "No debe verse «{$palabra}».");
        }
    }

    #[DataProvider('plantillasConEstilo')]
    public function test_el_estilo_visual_se_ofrece_solo_con_las_plantillas_a_a_d(string $plantilla): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $restaurante->update(['sitio_plantilla' => $plantilla]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->assertSee('Estilo del menú')
            ->assertSee('Editorial oscuro');
    }

    #[DataProvider('plantillasSinEstilo')]
    public function test_el_estilo_visual_no_se_muestra_con_las_plantillas_e_a_g(string $plantilla): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $restaurante->update(['sitio_plantilla' => $plantilla]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->assertDontSee('Estilo del menú')
            ->assertDontSee('Fondo crema, tipografía con serifa');
    }

    public function test_al_cambiar_de_plantilla_aparece_o_desaparece_el_estilo_sin_recargar(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->assertSee('Estilo del menú')
            ->set('data.sitio_plantilla', 'f')
            ->assertDontSee('Estilo del menú')
            ->set('data.sitio_plantilla', 'c')
            ->assertSee('Estilo del menú');
    }

    public function test_guardar_conserva_el_estilo_anterior_cuando_la_plantilla_no_lo_usa(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $restaurante->update(['tema' => 'rustico']);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->set('data.sitio_plantilla', 'g')
            ->call('save')
            ->assertHasNoErrors();

        $restaurante->refresh();
        $this->assertSame('g', $restaurante->sitio_plantilla);
        $this->assertSame('rustico', $restaurante->tema);
    }

    public function test_guarda_plantilla_estilo_y_color_principal(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->set('data.sitio_plantilla', 'd')
            ->set('data.tema', 'editorial')
            ->set('data.color_acento', '#c9a24a')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('Cambios guardados');

        $restaurante->refresh();
        $this->assertSame('d', $restaurante->sitio_plantilla);
        $this->assertSame('editorial', $restaurante->tema);
        $this->assertSame('#c9a24a', $restaurante->color_acento);
    }

    public function test_rechaza_un_estilo_o_un_color_invalidos(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->set('data.tema', 'neon')
            ->set('data.color_acento', 'rojo')
            ->call('save')
            ->assertHasErrors(['data.tema', 'data.color_acento']);

        $this->assertSame('calido', $restaurante->fresh()->tema);
        $this->assertNull($restaurante->fresh()->color_acento);
    }

    public function test_el_color_explica_cuando_se_aplica_y_cuando_no(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->assertSee('Sin color elegido')
            ->set('data.color_acento', '#a8541c')
            ->assertSee('tu color se usa en los botones y detalles de tu menú')
            ->set('data.sitio_plantilla', 'e')
            ->assertSee('Tu color se usará en los botones y detalles de tu sitio y de tu menú.')
            ->set('data.color_acento', '#ff8000')
            ->assertSee('no se leería bien con el texto de este diseño');
    }

    public function test_el_boton_usar_el_color_original_borra_el_color(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $restaurante->update(['color_acento' => '#a8541c']);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->assertSet('data.color_acento', '#a8541c')
            ->fillForm(['color_acento' => null])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($restaurante->fresh()->color_acento);
    }

    /**
     * @return array<string, array{0: string, 1: ?string, 2: string, 3: bool}>
     */
    public static function casosDeColor(): array
    {
        return [
            'sin color' => ['a', null, 'sin_color', false],
            'a-c: solo el menú' => ['b', '#a8541c', 'solo_menu', true],
            'd con dorado legible' => ['d', '#c9a24a', 'aplica', true],
            'd con color casi negro' => ['d', '#1a1a1a', 'no_legible', false],
            'e con arcilla' => ['e', '#a8541c', 'aplica', true],
            'e con naranja medio' => ['e', '#ff8000', 'no_legible', false],
            'f con azul marino' => ['f', '#0f172a', 'aplica', true],
            'f con el magenta del bar' => ['f', '#e11d9c', 'no_legible', false],
            'g con gris medio' => ['g', '#808080', 'no_legible', false],
            'h con verde albahaca' => ['h', '#3f6b3a', 'aplica', true],
            'h con gris medio' => ['h', '#808080', 'no_legible', false],
            'color mal formado' => ['e', 'rojo', 'sin_color', false],
        ];
    }

    #[DataProvider('casosDeColor')]
    public function test_estado_del_color_por_plantilla(string $plantilla, ?string $color, string $clave, bool $aplica): void
    {
        $estado = AparienciaSitio::estadoDelColor($plantilla, $color);

        $this->assertSame($aplica, $estado['aplica']);
        $this->assertSame(__("panel.apariencia.color.{$clave}"), $estado['mensaje']);
    }

    public function test_el_estado_del_color_coincide_con_la_regla_de_las_plantillas_e_f_g(): void
    {
        foreach (['#a8541c', '#f5f5a0', '#808080', '#e11d9c', '#0f172a', '#ffffff', '#5b6b3a'] as $color) {
            $restaurante = new Restaurante(['color_acento' => $color]);
            $texto = $restaurante->colorAcentoTexto();
            $luz = function (string $hex): float {
                $canales = array_map(fn (string $p): float => hexdec($p) / 255, str_split(ltrim($hex, '#'), 2));
                [$r, $g, $b] = array_map(fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $canales);

                return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
            };
            $contraste = (max($luz($color), $luz($texto)) + 0.05) / (min($luz($color), $luz($texto)) + 0.05);

            $this->assertSame($contraste >= 4.5, AparienciaSitio::estadoDelColor('e', $color)['aplica'], "Color {$color}");
        }
    }

    public function test_logotipo_y_portada_se_suben_desde_apariencia(): void
    {
        Storage::fake('public');
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->assertSee('Logotipo')
            ->assertSee('Fotografía de portada')
            ->set('data.logo', UploadedFile::fake()->image('logo.png', 300, 300))
            ->set('data.portada', UploadedFile::fake()->image('portada.jpg', 1600, 900))
            ->call('save')
            ->assertHasNoErrors();

        $restaurante->refresh();
        $this->assertStringStartsWith("restaurantes/{$restaurante->id}/", (string) $restaurante->logo);
        $this->assertStringStartsWith("restaurantes/{$restaurante->id}/", (string) $restaurante->portada);
    }

    public function test_solo_se_edita_el_restaurante_activo(): void
    {
        $a = $this->crearRestaurante('restaurante-a');
        $b = $this->crearRestaurante('restaurante-b');
        $this->entrarAlPanel($this->crearUsuario('propietario', $a), $a);

        Livewire::test(Apariencia::class)
            ->set('data.sitio_plantilla', 'f')
            ->call('save');

        $this->assertSame('f', $a->fresh()->sitio_plantilla);
        $this->assertSame('a', $b->fresh()->sitio_plantilla);
    }

    #[DataProvider('rolesSinAcceso')]
    public function test_los_demas_roles_no_ven_apariencia(string $rol): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario($rol, $restaurante), $restaurante);

        $this->assertFalse(Apariencia::canAccess());
        $this->assertNotContains('Apariencia', $this->etiquetasDeNavegacion());
        Livewire::test(Apariencia::class)->assertForbidden();
    }

    public function test_el_superadmin_conserva_el_acceso(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $restaurante);

        $this->assertTrue(Apariencia::canAccess());
    }

    public function test_el_propietario_ve_apariencia_en_la_navegacion(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $this->assertContains('Apariencia', $this->etiquetasDeNavegacion());
    }

    /**
     * @return list<string>
     */
    private function etiquetasDeNavegacion(): array
    {
        return collect(Filament::getNavigation())
            ->flatMap(fn ($grupo) => $grupo->getItems())
            ->map(fn ($item): string => (string) $item->getLabel())
            ->all();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesSinAcceso(): array
    {
        return ['editor' => ['editor'], 'gerente' => ['gerente'], 'recepcion' => ['recepcion'], 'cocina' => ['cocina']];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function plantillasConEstilo(): array
    {
        return ['a' => ['a'], 'b' => ['b'], 'c' => ['c'], 'd' => ['d']];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function plantillasSinEstilo(): array
    {
        return ['e' => ['e'], 'f' => ['f'], 'g' => ['g'], 'h' => ['h']];
    }
}
