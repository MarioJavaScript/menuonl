<?php

namespace Tests\Feature;

use App\Services\RespaldoService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

/**
 * Hora del respaldo (configurable, con zona horaria explícita), usuario de base de datos propio y suma de verificación.
 */
class RespaldoProgramacionYUsuarioTest extends TestCase
{
    private const PASSWORD = 'clave-de-prueba-123';

    private string $raiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'menuonl-prog-'.bin2hex(random_bytes(6));
        mkdir($this->raiz.'/storage/app/public/restaurantes', 0777, true);
        $this->app->useStoragePath($this->raiz.'/storage');
        Storage::fake('backups');
        config(['menuonl.respaldos.disco' => 'backups', 'menuonl.respaldos.password' => self::PASSWORD]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->borrar($this->raiz);
        parent::tearDown();
    }

    private function borrar(string $ruta): void
    {
        if (is_dir($ruta)) {
            foreach (array_diff(scandir($ruta), ['.', '..']) as $hijo) {
                $this->borrar($ruta.DIRECTORY_SEPARATOR.$hijo);
            }
            rmdir($ruta);
        } elseif (file_exists($ruta)) {
            unlink($ruta);
        }
    }

    private function eventoDelRespaldo(): Event
    {
        $eventos = collect($this->app->make(Schedule::class)->events())
            ->filter(fn (Event $evento): bool => str_contains((string) $evento->command, 'menuonl:backup'));

        $this->assertCount(1, $eventos);

        return $eventos->first();
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function horasConfiguradas(): array
    {
        return [
            'la de poco tráfico por defecto' => ['04:00', '04:00'],
            'otra hora válida' => ['05:30', '05:30'],
            'medianoche' => ['00:00', '00:00'],
            'sin definir' => [null, '04:00'],
            'vacía' => ['', '04:00'],
            'hora 24 inválida' => ['24:00', '04:00'],
            'sin ceros' => ['4:00', '04:00'],
            'con texto' => ['las cuatro', '04:00'],
            'minutos inválidos' => ['04:60', '04:00'],
        ];
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function zonasConfiguradas(): array
    {
        return [
            'Ciudad de México' => ['America/Mexico_City', 'America/Mexico_City'],
            'otra zona válida' => ['America/Tijuana', 'America/Tijuana'],
            'UTC' => ['UTC', 'UTC'],
            'sin definir' => [null, 'America/Mexico_City'],
            'vacía' => ['', 'America/Mexico_City'],
            'inventada' => ['Marte/Olympus', 'America/Mexico_City'],
        ];
    }

    #[DataProvider('horasConfiguradas')]
    public function test_la_hora_del_respaldo_se_lee_de_la_configuracion_y_un_valor_malo_cae_a_la_segura(?string $configurada, string $esperada): void
    {
        config(['menuonl.respaldos.hora' => $configurada]);

        $this->assertSame($esperada, RespaldoService::horaProgramada());
    }

    #[DataProvider('zonasConfiguradas')]
    public function test_la_zona_horaria_se_lee_de_la_configuracion_y_un_valor_malo_cae_a_mexico(?string $configurada, string $esperada): void
    {
        config(['menuonl.respaldos.zona_horaria' => $configurada]);

        $this->assertSame($esperada, RespaldoService::zonaHorariaProgramada());
    }

    public function test_por_defecto_el_respaldo_corre_a_las_4_de_la_manana_hora_de_ciudad_de_mexico(): void
    {
        $this->assertSame('04:00', config('menuonl.respaldos.hora'));
        $this->assertSame('America/Mexico_City', config('menuonl.respaldos.zona_horaria'));

        $evento = $this->eventoDelRespaldo();

        $this->assertSame('0 4 * * *', $evento->expression);
        $this->assertSame('America/Mexico_City', (string) $evento->timezone);
    }

    public function test_toca_a_las_10_utc_y_no_a_las_3_utc_que_eran_las_9_de_la_noche_en_mexico(): void
    {
        $evento = $this->eventoDelRespaldo();

        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00', 'UTC'));
        $this->assertTrue($evento->isDue($this->app));

        Carbon::setTestNow(Carbon::parse('2026-10-20 03:00:00', 'UTC'));
        $this->assertFalse($evento->isDue($this->app), 'Las 03:00 UTC son las 21:00 en México: hora de cena.');

        Carbon::setTestNow(Carbon::parse('2026-10-20 04:00:00', 'UTC'));
        $this->assertFalse($evento->isDue($this->app));
    }

    public function test_el_scheduler_es_independiente_de_la_zona_horaria_de_la_aplicacion(): void
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        $evento = $this->eventoDelRespaldo();

        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00', 'UTC'));

        $this->assertTrue($evento->isDue($this->app));
    }

    private function usarMariaDb(): void
    {
        config([
            'database.default' => 'mariadb',
            'database.connections.mariadb.host' => 'db.interno',
            'database.connections.mariadb.database' => 'menuonl',
            'database.connections.mariadb.username' => 'app_usuario',
            'database.connections.mariadb.password' => 'clave-de-la-aplicacion',
        ]);
        Process::fake(['*' => Process::result(output: '', errorOutput: 'Access denied', exitCode: 2)]);
    }

    public function test_sin_usuario_de_respaldo_mysqldump_usa_el_de_la_aplicacion(): void
    {
        $this->usarMariaDb();
        config(['menuonl.respaldos.db_usuario' => null, 'menuonl.respaldos.db_password' => null]);

        $this->artisan('menuonl:backup')->assertExitCode(1);

        Process::assertRan(fn ($p): bool => in_array('--user=app_usuario', (array) $p->command, true)
            && ($p->environment['MYSQL_PWD'] ?? null) === 'clave-de-la-aplicacion');
    }

    public function test_con_usuario_de_respaldo_mysqldump_usa_ese_usuario_y_su_clave_por_entorno(): void
    {
        $this->usarMariaDb();
        config(['menuonl.respaldos.db_usuario' => 'respaldo_ro', 'menuonl.respaldos.db_password' => 'clave-de-solo-lectura']);

        $this->artisan('menuonl:backup')->assertExitCode(1);

        Process::assertRan(fn ($p): bool => in_array('--user=respaldo_ro', (array) $p->command, true)
            && ($p->environment['MYSQL_PWD'] ?? null) === 'clave-de-solo-lectura'
            && ! str_contains(implode(' ', (array) $p->command), 'clave-de-solo-lectura'));
        Process::assertNotRan(fn ($p): bool => in_array('--user=app_usuario', (array) $p->command, true));
    }

    public function test_la_salida_del_respaldo_fallido_no_imprime_ninguna_clave(): void
    {
        $this->usarMariaDb();
        config(['menuonl.respaldos.db_usuario' => 'respaldo_ro', 'menuonl.respaldos.db_password' => 'clave-de-solo-lectura']);

        $this->artisan('menuonl:backup')
            ->doesntExpectOutputToContain('clave-de-solo-lectura')
            ->doesntExpectOutputToContain('clave-de-la-aplicacion')
            ->doesntExpectOutputToContain(self::PASSWORD)
            ->assertExitCode(1);
    }

    public function test_un_respaldo_fallido_deja_un_registro_de_error(): void
    {
        $this->usarMariaDb();
        Log::spy();

        $this->artisan('menuonl:backup')->assertExitCode(1);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_el_manifest_trae_la_suma_sha256_del_volcado(): void
    {
        $sqlite = $this->raiz.'/base.sqlite';
        $pdo = new PDO('sqlite:'.$sqlite);
        $pdo->exec('CREATE TABLE restaurantes (id INTEGER PRIMARY KEY, nombre TEXT)');
        $pdo = null;
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $sqlite]);

        $this->artisan('menuonl:backup')->assertExitCode(0);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('backups')->path(Storage::disk('backups')->files()[0])));
        $zip->setPassword(self::PASSWORD);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $volcado = (string) $zip->getFromName('database.sqlite');
        $zip->close();

        $this->assertSame(hash('sha256', $volcado), $manifest['sha256_base']);
    }
}
