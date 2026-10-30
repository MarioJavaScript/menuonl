<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;
use ZipArchive;

class RespaldoMenuonlTest extends TestCase
{
    private const PASSWORD = 'clave-de-prueba-123';

    private string $raiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'menuonl-test-'.bin2hex(random_bytes(6));
        mkdir($this->raiz.'/storage/app/public/restaurantes/1/productos', 0777, true);
        file_put_contents($this->raiz.'/storage/app/public/restaurantes/1/productos/foto.webp', 'imagen-falsa');
        file_put_contents($this->raiz.'/storage/app/public/restaurantes/.env.local', 'SECRETO');
        $this->app->useStoragePath($this->raiz.'/storage');

        $sqlite = $this->raiz.'/base.sqlite';
        $pdo = new PDO('sqlite:'.$sqlite);
        $pdo->exec("CREATE TABLE restaurantes (id INTEGER PRIMARY KEY, nombre TEXT); INSERT INTO restaurantes (nombre) VALUES ('Café Ñandú')");
        $pdo = null;

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $sqlite,
            'menuonl.respaldos.password' => self::PASSWORD,
            'menuonl.respaldos.disco' => 'backups',
        ]);
        Storage::fake('backups');
    }

    protected function tearDown(): void
    {
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

    /**
     * @return array<int, string>
     */
    private function respaldosEnDisco(): array
    {
        return Storage::disk('backups')->files();
    }

    public function test_crea_el_respaldo_en_el_disco_con_la_base_y_las_imagenes_sin_secretos(): void
    {
        $this->artisan('menuonl:backup')->assertExitCode(0);

        $archivos = $this->respaldosEnDisco();
        $this->assertCount(1, $archivos);
        $this->assertMatchesRegularExpression('/^menuonl-\d{8}-\d{6}\.zip$/', $archivos[0]);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('backups')->path($archivos[0])));
        $zip->setPassword(self::PASSWORD);
        $nombres = array_map(fn (int $i): string => $zip->getNameIndex($i), range(0, $zip->numFiles - 1));

        $this->assertEqualsCanonicalizing(['database.sqlite', 'restaurantes/1/productos/foto.webp', 'manifest.json'], $nombres);
        $this->assertSame('imagen-falsa', $zip->getFromName('restaurantes/1/productos/foto.webp'));
        $this->assertSame(1, json_decode($zip->getFromName('manifest.json'), true)['imagenes']);
        $zip->close();
    }

    public function test_el_archivo_esta_cifrado_con_aes_256_y_no_se_lee_sin_la_clave(): void
    {
        $this->artisan('menuonl:backup')->assertExitCode(0);

        $zip = new ZipArchive;
        $zip->open(Storage::disk('backups')->path($this->respaldosEnDisco()[0]));

        foreach (range(0, $zip->numFiles - 1) as $indice) {
            $this->assertSame(ZipArchive::EM_AES_256, $zip->statIndex($indice)['encryption_method']);
        }

        $this->assertFalse($zip->getFromName('restaurantes/1/productos/foto.webp'));
        $zip->setPassword('otra-clave');
        $this->assertFalse($zip->getFromName('restaurantes/1/productos/foto.webp'));

        $crudo = file_get_contents(Storage::disk('backups')->path($this->respaldosEnDisco()[0]));
        $this->assertStringNotContainsString('imagen-falsa', $crudo);
        $this->assertStringNotContainsString('Ñandú', $crudo);
        $zip->close();
    }

    public function test_sin_contrasena_falla_con_codigo_distinto_de_cero_y_no_sube_nada(): void
    {
        config(['menuonl.respaldos.password' => '']);
        Log::spy();

        $this->artisan('menuonl:backup')->assertExitCode(1);

        $this->assertSame([], $this->respaldosEnDisco());
        Log::shouldHaveReceived('error')->once();
    }

    public function test_la_retencion_borra_solo_los_respaldos_de_mas_de_14_dias_con_el_patron_del_comando(): void
    {
        $ahora = CarbonImmutable::now('UTC');
        $disco = Storage::disk('backups');
        $viejo = 'menuonl-'.$ahora->subDays(15)->format('Ymd-His').'.zip';
        $limite = 'menuonl-'.$ahora->subDays(13)->format('Ymd-His').'.zip';
        $reciente = 'menuonl-'.$ahora->subDay()->format('Ymd-His').'.zip';

        foreach ([$viejo, $limite, $reciente, 'otro-archivo.zip', 'menuonl-19990101-000000.txt'] as $nombre) {
            $disco->put($nombre, 'x');
        }

        $this->artisan('menuonl:backup')->assertExitCode(0);

        $disco->assertMissing($viejo);
        $disco->assertExists([$limite, $reciente, 'otro-archivo.zip', 'menuonl-19990101-000000.txt']);
        $this->assertCount(5, $this->respaldosEnDisco());
    }

    public function test_si_el_disco_rechaza_la_subida_falla_sin_reportar_exito_ni_aplicar_retencion(): void
    {
        $disco = \Mockery::mock(Filesystem::class);
        $disco->shouldReceive('writeStream')->once()->andReturn(false);
        $disco->shouldReceive('exists')->andReturn(false);
        $disco->shouldNotReceive('delete');
        Storage::shouldReceive('disk')->with('backups')->andReturn($disco);
        Log::spy();

        $this->artisan('menuonl:backup')->assertExitCode(1);

        Log::shouldHaveReceived('error')->once();
        Log::shouldNotHaveReceived('info');
    }

    public function test_si_la_subida_queda_incompleta_falla(): void
    {
        $disco = \Mockery::mock(Filesystem::class);
        $disco->shouldReceive('writeStream')->once()->andReturn(true);
        $disco->shouldReceive('exists')->andReturn(true);
        $disco->shouldReceive('size')->andReturn(1);
        Storage::shouldReceive('disk')->with('backups')->andReturn($disco);

        $this->artisan('menuonl:backup')->assertExitCode(1);
    }

    public function test_si_falla_el_borrado_de_la_retencion_el_comando_falla(): void
    {
        $viejo = 'menuonl-'.CarbonImmutable::now('UTC')->subDays(30)->format('Ymd-His').'.zip';
        Storage::disk('backups')->put($viejo, 'x');
        $real = Storage::disk('backups');
        $disco = \Mockery::mock($real)->makePartial();
        $disco->shouldReceive('delete')->andReturn(false);
        Storage::shouldReceive('disk')->with('backups')->andReturn($disco);
        Log::spy();

        $this->artisan('menuonl:backup')->assertExitCode(1);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_con_mariadb_usa_mysqldump_con_la_clave_por_entorno_y_falla_si_mysqldump_falla(): void
    {
        config([
            'database.default' => 'mariadb',
            'database.connections.mariadb.host' => 'db.interno',
            'database.connections.mariadb.database' => 'menuonl',
            'database.connections.mariadb.username' => 'respaldo',
            'database.connections.mariadb.password' => 'secreta',
        ]);
        Process::fake(['*' => Process::result(output: '', errorOutput: 'Access denied', exitCode: 2)]);

        $this->artisan('menuonl:backup')->assertExitCode(1);

        Process::assertRan(function ($proceso): bool {
            $comando = is_array($proceso->command) ? implode(' ', $proceso->command) : $proceso->command;

            return str_contains($comando, 'mysqldump')
                && str_contains($comando, '--single-transaction')
                && str_contains($comando, '--host=db.interno')
                && ! str_contains($comando, 'secreta')
                && ($proceso->environment['MYSQL_PWD'] ?? null) === 'secreta';
        });
        $this->assertSame([], $this->respaldosEnDisco());
    }

    public function test_la_verificacion_descifra_y_valida_el_ultimo_respaldo(): void
    {
        $this->artisan('menuonl:backup')->assertExitCode(0);

        $this->artisan('menuonl:backup-verify')
            ->expectsOutputToContain('verificado')
            ->assertExitCode(0);
    }

    public function test_la_verificacion_falla_con_clave_incorrecta_o_sin_respaldos(): void
    {
        $this->artisan('menuonl:backup-verify')->assertExitCode(1);

        $this->artisan('menuonl:backup')->assertExitCode(0);
        config(['menuonl.respaldos.password' => 'clave-equivocada']);

        $this->artisan('menuonl:backup-verify')->assertExitCode(1);
    }

    public function test_el_scheduler_registra_el_respaldo_diario(): void
    {
        $eventos = collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($evento): bool => preg_match('/menuonl:backup(?!-)/', (string) $evento->command) === 1);

        $this->assertCount(1, $eventos);
        $this->assertSame('0 4 * * *', $eventos->first()->expression);
        $this->assertSame('America/Mexico_City', (string) $eventos->first()->timezone);
        $this->assertTrue($eventos->first()->withoutOverlapping);
    }
}
