<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tests\TestCase;

/**
 * La ruta /sitio/{slug} ya no depende de APP_ENV: la aplicacion se arranca como produccion, sin base de datos
 * (en produccion los comandos destructivos de las migraciones estan prohibidos).
 */
class SitioPublicoEntornoTest extends TestCase
{
    public function createApplication(): Application
    {
        $previo = getenv('APP_ENV');
        $envPrevio = $_ENV['APP_ENV'] ?? null;
        $serverPrevio = $_SERVER['APP_ENV'] ?? null;

        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';

        try {
            $app = require base_path('bootstrap/app.php');
            $app->make(Kernel::class)->bootstrap();
        } finally {
            $previo === false ? putenv('APP_ENV') : putenv("APP_ENV={$previo}");
            $envPrevio === null ? $_ENV['APP_ENV'] = null : $_ENV['APP_ENV'] = $envPrevio;
            $serverPrevio === null ? $_SERVER['APP_ENV'] = null : $_SERVER['APP_ENV'] = $serverPrevio;
            if ($envPrevio === null) {
                unset($_ENV['APP_ENV']);
            }
            if ($serverPrevio === null) {
                unset($_SERVER['APP_ENV']);
            }
        }

        return $app;
    }

    public function test_la_ruta_del_sitio_existe_en_produccion(): void
    {
        $this->assertTrue($this->app->environment('production'));

        $ruta = $this->app['router']->getRoutes()->getByName('sitio.prototipo');

        $this->assertNotNull($ruta);
        $this->assertSame('sitio/{slug}', $ruta->uri());
        $this->assertContains('throttle:menu-publico', $ruta->gatherMiddleware());
    }

    public function test_el_archivo_de_rutas_ya_no_condiciona_el_sitio_al_entorno(): void
    {
        $archivo = (string) file_get_contents(base_path('routes/web.php'));

        $this->assertStringNotContainsString('environment(', $archivo);
    }
}
