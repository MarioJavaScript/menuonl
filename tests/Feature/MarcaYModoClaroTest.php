<?php

namespace Tests\Feature;

use App\Mail\InvitacionMail;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Services\InvitacionService;
use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Nombre visible de la plataforma (un solo valor: config('app.name')) y modo claro por defecto.
 * Los identificadores internos (comandos, claves de localStorage, variables de entorno) no cambian con la marca.
 */
class MarcaYModoClaroTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const string MARCA = 'Marca Fija de Prueba';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
        config(['app.name' => self::MARCA]);
    }

    private function menuActivo(): Restaurante
    {
        $restaurante = Restaurante::factory()->create(['slug' => 'marca-fija', 'nombre' => 'Fonda Fija', 'estado' => 'activo']);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'es']);

        return $restaurante;
    }

    public function test_el_valor_de_marca_por_defecto_es_menubly(): void
    {
        $this->assertStringContainsString("env('APP_NAME', 'Menubly')", (string) file_get_contents(config_path('app.php')));
        $this->assertStringContainsString('APP_NAME=Menubly', (string) file_get_contents(base_path('.env.example')));
    }

    public function test_la_portada_muestra_la_marca_configurada(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(self::MARCA.' — Menú Digital Interactivo', false)
            ->assertSee(self::MARCA.'. Todos los derechos', false)
            ->assertDontSee('MENUONL');
    }

    public function test_el_menu_publico_muestra_la_marca_en_el_pie(): void
    {
        $restaurante = $this->menuActivo();

        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))
            ->assertOk()
            ->assertSee('Hecho con')
            ->assertSee('alt="'.self::MARCA.'"', false)
            ->assertDontSee('MENUONL');
    }

    public function test_la_pantalla_de_menu_no_disponible_muestra_la_marca(): void
    {
        $restaurante = Restaurante::factory()->create(['slug' => 'marca-suspendida', 'estado' => 'suspendido']);

        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))
            ->assertNotFound()
            ->assertSee('Menú no disponible — '.self::MARCA, false)
            ->assertSee('Ir a la portada de '.self::MARCA);
    }

    public function test_las_invitaciones_muestran_la_marca(): void
    {
        $this->get('/invitaciones/token-que-no-existe')->assertSee(self::MARCA)->assertDontSee('MENUONL');
    }

    public function test_el_panel_usa_la_marca_en_el_encabezado_y_el_titulo_de_la_pestana(): void
    {
        $this->assertSame(self::MARCA, Filament::getPanel('admin')->getBrandName());

        $this->get('/admin/login')->assertOk()->assertSee(self::MARCA)->assertDontSee('MENUONL');
        $this->assertStringContainsString(self::MARCA, view('filament.logo')->render());
    }

    public function test_los_textos_de_los_planes_y_el_correo_de_invitacion_usan_la_marca(): void
    {
        $this->assertStringContainsString(self::MARCA, __('panel.planes.sitio_menu.descripcion'));
        $this->assertStringContainsString(self::MARCA, __('panel.planes.solo_menu.descripcion'));

        Mail::fake();
        $restaurante = $this->crearRestaurante('marca-correo');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        app(InvitacionService::class)->invitar($restaurante, 'invitada@ejemplo.com', 'propietario', $propietario);

        Mail::assertSent(InvitacionMail::class, fn (InvitacionMail $mail): bool => str_contains($mail->render(), self::MARCA));
    }

    public function test_los_identificadores_internos_no_cambiaron(): void
    {
        $restaurante = $this->menuActivo();
        $html = $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk()->getContent();
        $this->assertStringContainsString('menuonl.css', $html);

        $menu = (string) file_get_contents(resource_path('views/menu/publico.blade.php'));
        $this->assertStringContainsString("'menuonl_cart_m_'.", $menu);
        $this->assertStringContainsString("'menuonl_cart_'.", $menu);
        $this->assertStringContainsString("'menuonl_cart_m'", (string) file_get_contents(resource_path('js/menu-pedido.js')));
        $this->assertFileExists(config_path('menuonl.php'));
        $this->assertNotNull(config('menuonl.imagenes.max_bytes'));

        $comandos = array_keys(Artisan::all());
        foreach (['menuonl:backup', 'menuonl:respaldar', 'menuonl:backup-verify', 'menuonl:verificar-respaldo', 'menuonl:restaurar-respaldo', 'menuonl:verificar-produccion', 'menuonl:crear-superadmin'] as $comando) {
            $this->assertContains($comando, $comandos);
        }

        $ejemplo = (string) file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('CLOUDFLARE_DOMAIN=menuonl.com', $ejemplo);
        $this->assertStringContainsString('BACKUP_PREFIX=menuonl', $ejemplo);
        $this->assertStringContainsString("var clave = 'filament:theme:mode';", (string) file_get_contents(public_path('js/apariencia.js')));
    }

    public function test_sin_preferencia_guardada_el_menu_sale_en_claro(): void
    {
        $restaurante = $this->menuActivo();
        $html = $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-apariencia="dark"', $html);
        $this->assertStringNotContainsString('data-apariencia="system"', $html);
        $this->assertStringContainsString('<meta name="color-scheme" content="light">', $html);
        $this->assertMatchesRegularExpression('/value="light"\s+data-apariencia-opcion\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="system"\s+data-apariencia-opcion\s+checked/', $html);
    }

    public function test_la_hoja_compartida_solo_sigue_al_sistema_con_automatico_elegido(): void
    {
        $css = (string) file_get_contents(public_path('css/menuonl.css'));

        $this->assertStringNotContainsString(':root:not([data-apariencia="light"])', $css);
        $this->assertStringContainsString(':root[data-apariencia="system"]', $css);
        $this->assertStringContainsString(':root[data-apariencia="dark"]', $css);

        preg_match_all('/@media \(prefers-color-scheme: dark\) \{\s*([^{]+)\{/', $css, $coincidencias);
        $this->assertNotEmpty($coincidencias[1]);
        foreach ($coincidencias[1] as $selector) {
            $this->assertStringContainsString('[data-apariencia="system"]', $selector);
        }
    }

    public function test_el_panel_de_filament_arranca_en_claro_y_conserva_la_clave_de_preferencia(): void
    {
        $this->assertSame(ThemeMode::Light, Filament::getPanel('admin')->getDefaultThemeMode());

        $html = $this->get('/admin/login')->assertOk()->getContent();
        $this->assertStringContainsString('filament:theme:mode', $html);
        $this->assertMatchesRegularExpression('/filament:theme:mode.{0,3}\)\s*\?\?\s*\S{0,10}light/', $html);
    }

    public function test_la_pantalla_de_plan_sin_panel_se_ve_en_claro_y_respeta_la_preferencia(): void
    {
        $vista = str_replace("\r\n", "\n", (string) file_get_contents(resource_path('views/plan/sin-panel.blade.php')));

        $this->assertStringContainsString('content="light"', $vista);
        $this->assertStringContainsString(':root[data-apariencia="dark"]', $vista);
        $this->assertStringContainsString("@media (prefers-color-scheme: dark) {\n            :root[data-apariencia=\"system\"]", $vista);
        $this->assertStringContainsString('js/apariencia.js', $vista);
    }
}
