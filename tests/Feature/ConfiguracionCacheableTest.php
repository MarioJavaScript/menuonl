<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * `php artisan config:cache` es parte del despliegue: cualquier objeto o closure dentro de la configuración lo rompe.
 */
class ConfiguracionCacheableTest extends TestCase
{
    /**
     * Rutas (con notación de puntos) que pueden contener un objeto a propósito. Hoy no hay ninguna: añadir una exige
     * justificar aquí por qué `config:cache` la soporta.
     *
     * @var list<string>
     */
    private const EXCEPCIONES = [];

    /** @var list<string> */
    private array $temporales = [];

    protected function tearDown(): void
    {
        foreach ($this->temporales as $ruta) {
            @unlink($ruta);
        }

        parent::tearDown();
    }

    private function rutaTemporal(string $prefijo): string
    {
        $ruta = tempnam(sys_get_temp_dir(), $prefijo);
        $this->temporales[] = $ruta;

        return $ruta;
    }

    /**
     * Un archivo de caché vacío ya existente se leería como configuración cacheada, así que solo se reserva el nombre.
     */
    private function rutaDeCacheInexistente(): string
    {
        $ruta = $this->rutaTemporal('cfg');
        unlink($ruta);

        return $ruta;
    }

    /**
     * Laravel antepone la carpeta del proyecto a `APP_CONFIG_CACHE` salvo que empiece por `/` o `\`, y en Windows una
     * ruta con letra de unidad no cuenta como absoluta.
     */
    private function comoRutaDeCache(string $ruta): string
    {
        return preg_replace('/^[A-Za-z]:/', '', $ruta);
    }

    /**
     * @param  list<string>  $comando
     * @param  array<string, string>  $entorno
     */
    private function ejecutar(array $comando, array $entorno): Process
    {
        $proceso = new Process([PHP_BINARY, ...$comando], base_path(), $entorno, null, 120);
        $proceso->run();

        return $proceso;
    }

    public function test_la_configuracion_no_contiene_objetos_ni_closures(): void
    {
        $problemas = [];

        foreach (Arr::dot(config()->all()) as $ruta => $valor) {
            if (is_object($valor) && ! in_array($ruta, self::EXCEPCIONES, true)) {
                $problemas[] = $ruta.' ('.get_debug_type($valor).')';
            }
        }

        $this->assertSame([], $problemas, 'Valores no serializables en la configuración: '.implode(', ', $problemas));
    }

    public function test_config_cache_termina_bien_en_un_proceso_aparte_sin_tocar_bootstrap_cache(): void
    {
        $archivo = $this->rutaDeCacheInexistente();
        $antes = glob(base_path('bootstrap/cache/*')) ?: [];

        $proceso = $this->ejecutar(['artisan', 'config:cache', '--no-interaction'], ['APP_CONFIG_CACHE' => $this->comoRutaDeCache($archivo)]);

        try {
            $this->assertSame(0, $proceso->getExitCode(), $proceso->getOutput().$proceso->getErrorOutput());
            $this->assertStringContainsString('subida_recibida', (string) file_get_contents($archivo));
            $this->assertSame($antes, glob(base_path('bootstrap/cache/*')) ?: []);
        } finally {
            $this->ejecutar(['artisan', 'config:clear', '--no-interaction'], ['APP_CONFIG_CACHE' => $this->comoRutaDeCache($archivo)]);
        }
    }

    public function test_la_subida_temporal_conserva_la_regla_y_los_mensajes_con_la_configuracion_cacheada(): void
    {
        $archivo = $this->rutaDeCacheInexistente();
        $guion = $this->rutaTemporal('peticion');
        $entorno = ['APP_CONFIG_CACHE' => $this->comoRutaDeCache($archivo)];

        file_put_contents($guion, <<<'PHP'
<?php

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

require getcwd().'/vendor/autoload.php';

$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ruta = tempnam(sys_get_temp_dir(), 'sub');
file_put_contents($ruta, 'contenido');

$url = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), [], absolute: false);
$peticion = Request::create($url, 'POST', [], [], ['files' => [new UploadedFile($ruta, 'foto.jpg', 'image/jpeg', UPLOAD_ERR_PARTIAL, true)]], ['HTTP_ACCEPT' => 'application/json']);
$respuesta = $app->handle($peticion);

echo json_encode([
    'cacheada' => $app->configurationIsCached(),
    'reglas' => FileUploadConfiguration::rules(),
    'preview_mimes' => count(config('livewire.temporary_file_upload.preview_mimes')),
    'estado' => $respuesta->getStatusCode(),
    'errores' => json_decode($respuesta->getContent(), true)['errors'] ?? null,
    'esperado' => app(App\Services\WebpImageService::class)->mensajeSubidaIncompleta(),
]);

unlink($ruta);
PHP);

        try {
            $cache = $this->ejecutar(['artisan', 'config:cache', '--no-interaction'], $entorno);
            $this->assertSame(0, $cache->getExitCode(), $cache->getOutput().$cache->getErrorOutput());

            $peticion = $this->ejecutar([$guion], $entorno);
            $this->assertSame(0, $peticion->getExitCode(), $peticion->getOutput().$peticion->getErrorOutput());

            $resultado = json_decode(trim($peticion->getOutput()), true);

            $this->assertTrue($resultado['cacheada']);
            $this->assertSame(['subida_recibida'], $resultado['reglas']);
            $this->assertSame(16, $resultado['preview_mimes']);
            $this->assertSame(422, $resultado['estado']);
            $this->assertSame(['files.0' => [$resultado['esperado']]], $resultado['errores']);
        } finally {
            $this->ejecutar(['artisan', 'config:clear', '--no-interaction'], $entorno);
        }
    }
}
