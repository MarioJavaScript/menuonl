<?php

namespace Tests\Feature;

use App\Models\Restaurante;
use App\Services\PoliticaDeContenido;
use FilesystemIterator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Tras borrar el código muerto del panel viejo: nada apunta a archivos que ya no existen, el build solo pide archivos reales,
 * las rutas no conservan controladores ni middleware retirados y las invitaciones mantienen su política estricta.
 */
class LimpiezaDelPanelViejoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function archivosDeCodigo(): array
    {
        $archivos = [];

        foreach (['app', 'bootstrap', 'config', 'database', 'resources', 'routes'] as $carpeta) {
            $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($carpeta), FilesystemIterator::SKIP_DOTS));

            foreach ($iterador as $archivo) {
                if (preg_match('/\.(php|js|css|mjs)$/', $archivo->getFilename())) {
                    $archivos[] = $archivo->getPathname();
                }
            }
        }

        $this->assertNotEmpty($archivos);

        return $archivos;
    }

    public function test_ningun_archivo_referencia_los_recursos_borrados_del_panel_viejo(): void
    {
        $retirados = ['resources/js/panel.js', 'resources/css/panel.css', "view('panel.index'", 'view("panel.index"', "'panel.index'"];

        foreach ($this->archivosDeCodigo() as $ruta) {
            $contenido = (string) file_get_contents($ruta);

            foreach ($retirados as $texto) {
                $this->assertStringNotContainsString($texto, $contenido, "{$ruta} aún menciona {$texto}");
            }
        }

        $this->assertFileDoesNotExist(base_path('resources/js/panel.js'));
        $this->assertFileDoesNotExist(base_path('resources/css/panel.css'));
        $this->assertFileDoesNotExist(base_path('resources/views/panel/index.blade.php'));
    }

    public function test_todas_las_entradas_de_vite_existen_en_disco(): void
    {
        $configuracion = (string) file_get_contents(base_path('vite.config.js'));

        preg_match_all("#'(resources/[^']+)'#", $configuracion, $coincidencias);

        $this->assertNotEmpty($coincidencias[1]);

        foreach ($coincidencias[1] as $entrada) {
            $this->assertFileExists(base_path($entrada), "vite.config.js pide {$entrada}, que no existe.");
        }
    }

    public function test_las_rutas_no_conservan_controladores_ni_middleware_del_panel_viejo(): void
    {
        $retirados = ['Api\V1\Panel', 'ModuloPanel', 'ContextoRestaurantePanel', 'throttle:panel-api'];

        foreach (Route::getRoutes() as $ruta) {
            $texto = $ruta->getActionName().' '.implode(' ', array_filter($ruta->gatherMiddleware(), 'is_string'));

            foreach ($retirados as $retirado) {
                $this->assertStringNotContainsString($retirado, $texto, "{$ruta->uri()} usa {$retirado}");
            }
        }

        foreach (['app/Http/Middleware/ModuloPanel.php', 'app/Http/Middleware/ContextoRestaurantePanel.php', 'app/Http/Controllers/Api/V1/Panel', 'app/Http/Resources/Api/V1/Panel'] as $retirado) {
            $this->assertFileDoesNotExist(base_path($retirado));
            $this->assertDirectoryDoesNotExist(base_path($retirado));
        }

        $this->assertNull(RateLimiter::limiter('panel-api'));
    }

    public function test_solo_quedan_las_dos_rutas_de_la_puerta(): void
    {
        $nombres = collect(Route::getRoutes())
            ->filter(fn ($ruta): bool => str_starts_with($ruta->uri(), 'panel') || str_starts_with($ruta->uri(), 'api/v1/panel'))
            ->map(fn ($ruta): string => (string) $ruta->getName())
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['api.v1.panel.retirado', 'panel'], $nombres);
    }

    public function test_una_invitacion_conserva_su_politica_estricta_y_la_puerta_no_la_lleva(): void
    {
        $estricta = app(PoliticaDeContenido::class)->paraEntorno(app(PoliticaDeContenido::class)->estricta());

        $this->assertSame($estricta, $this->get('/invitaciones/token-que-no-existe')->headers->get('Content-Security-Policy'));
        $this->assertNotSame($estricta, $this->get('/panel')->headers->get('Content-Security-Policy'));
    }

    public function test_la_puerta_sigue_respondiendo_igual(): void
    {
        $this->get('/panel')->assertStatus(302)->assertRedirect('/admin');
        $this->post('/panel/reservas')->assertStatus(302)->assertRedirect('/admin');
        $this->getJson('/api/v1/panel/la-esquina/ajustes')->assertStatus(410)->assertExactJson(['message' => 'Esta versión del panel ya no existe. Recarga la página.']);
        $this->deleteJson('/api/v1/panel')->assertStatus(410);
    }

    public function test_las_rutas_publicas_vecinas_no_se_afectan(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]))->assertOk();
        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk();
    }
}
