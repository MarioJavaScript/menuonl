<?php

namespace Tests\Feature;

use App\Services\RespaldoService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class RespaldoAdversarialTest extends TestCase
{
    private const PASSWORD = 'clave-adversarial-segura-123';

    private string $raiz;

    private string $sqlitePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'menuonl-adv-test-'.bin2hex(random_bytes(6));
        mkdir($this->raiz.'/storage/app/public/restaurantes/1/productos', 0777, true);
        file_put_contents($this->raiz.'/storage/app/public/restaurantes/1/productos/foto.webp', 'imagen-falsa');
        $this->app->useStoragePath($this->raiz.'/storage');

        $this->sqlitePath = $this->raiz.'/base.sqlite';
        $pdo = new PDO('sqlite:'.$this->sqlitePath);
        $pdo->exec("CREATE TABLE restaurantes (id INTEGER PRIMARY KEY, nombre TEXT); INSERT INTO restaurantes (nombre) VALUES ('Café Ñandú')");
        $pdo = null;

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->sqlitePath,
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
     * @param  array<string, string>  $entries
     */
    private function crearZipManipulado(string $nombre, array $entries, ?string $password = self::PASSWORD): void
    {
        $tempZip = sys_get_temp_dir().DIRECTORY_SEPARATOR.'temp_zip_'.bin2hex(random_bytes(6)).'.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        if ($password !== null) {
            $zip->setPassword($password);
        }

        foreach ($entries as $entrada => $contenido) {
            $zip->addFromString($entrada, $contenido);
            if ($password !== null) {
                $zip->setEncryptionName($entrada, ZipArchive::EM_AES_256);
            }
        }

        $zip->close();

        Storage::disk('backups')->put($nombre, (string) file_get_contents($tempZip));
        unlink($tempZip);
    }

    /**
     * Test Zip Slip containment: archive with entry ../../evil.txt throws RuntimeException,
     * zero files extracted outside temp directory.
     */
    public function test_zip_slip_containment_con_ruta_relativa_lanza_excepcion_y_no_extrae_archivos(): void
    {
        $nombre = 'menuonl-20261018-010101.zip';
        $evilFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'evil.txt';
        $parentEvilFile = dirname(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'evil.txt';

        if (file_exists($evilFile)) {
            unlink($evilFile);
        }
        if (file_exists($parentEvilFile)) {
            unlink($parentEvilFile);
        }

        $this->crearZipManipulado($nombre, [
            '../../evil.txt' => 'CONTENIDO_MALICIOSO_ZIP_SLIP',
            'manifest.json' => (string) json_encode(['base_de_datos' => 'database.sqlite', 'imagenes' => 0]),
            'database.sqlite' => (string) file_get_contents($this->sqlitePath),
        ]);

        try {
            app(RespaldoService::class)->verificar($nombre);
            $this->fail('Se esperaba una RuntimeException al detectar Zip Slip.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Ruta no autorizada', $e->getMessage());
        }

        $this->assertFileDoesNotExist($evilFile, 'El archivo malicioso no debe extraerse en sys_get_temp_dir().');
        $this->assertFileDoesNotExist($parentEvilFile, 'El archivo malicioso no debe extraerse en el padre del temp dir.');
    }

    /**
     * Test manifest path traversal: manifest.json with "base_de_datos": "../../../etc/passwd"
     * rejected with RuntimeException.
     */
    public function test_manifest_con_path_traversal_en_base_de_datos_es_rechazado_con_runtime_exception(): void
    {
        $nombre = 'menuonl-20261018-020202.zip';

        $this->crearZipManipulado($nombre, [
            'manifest.json' => (string) json_encode([
                'creado_en' => '2026-10-18T00:00:00Z',
                'base_de_datos' => '../../../etc/passwd',
                'imagenes' => 0,
            ]),
            'database.sqlite' => (string) file_get_contents($this->sqlitePath),
        ]);

        $this->expectException(RuntimeException::class);
        app(RespaldoService::class)->verificar($nombre);
    }

    /**
     * Test mysqldump command options: -- delimiter protects database name like --tables
     * using Process::fake().
     */
    public function test_mysqldump_delimitador_doble_guion_protege_nombre_de_base_de_datos_y_rechaza_opciones(): void
    {
        // 1. Nombre de base con formato de opción (e.g. '--tables') se rechaza por validación estricta de regex
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.database' => '--tables',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => 'secreto',
            'menuonl.respaldos.mysqldump_path' => 'mysqldump',
            'menuonl.respaldos.timeout_segundos' => 60,
        ]);

        try {
            app(RespaldoService::class)->crear();
            $this->fail('Se esperaba que el nombre malicioso --tables fuera rechazado.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('inválido', $e->getMessage());
        }

        // 2. Nombre de base válido: el comando invocado incluye el delimitador '--' inmediatamente antes del nombre de base
        config([
            'database.connections.mysql.database' => 'menuonl_segura',
        ]);

        Process::fake(function (PendingProcess $process) {
            foreach ($process->command as $arg) {
                if (is_string($arg) && str_starts_with($arg, '--result-file=')) {
                    $destino = substr($arg, strlen('--result-file='));
                    file_put_contents($destino, 'CREATE TABLE usuarios (id INT);');
                }
            }

            return Process::result();
        });

        $resultado = app(RespaldoService::class)->crear();
        $this->assertNotEmpty($resultado['nombre']);

        Process::assertRan(function ($process) {
            $comando = (array) $process->command;
            $idxSeparador = array_search('--', $comando, true);
            $idxBase = array_search('menuonl_segura', $comando, true);

            return $idxSeparador !== false && $idxBase === ($idxSeparador + 1);
        });
    }

    /**
     * Test temp file cleanup: verify no lingering directories matching menuonl-respaldo-*
     * remain in temp dir when exceptions occur in creation or verification.
     */
    public function test_limpieza_de_directorios_temporales_ante_excepciones_en_creacion_y_verificacion(): void
    {
        $contarCarpetasRespaldo = function (): int {
            $archivos = scandir(sys_get_temp_dir()) ?: [];

            return count(array_filter($archivos, fn (string $f): bool => str_starts_with($f, 'menuonl-respaldo-')));
        };

        $directoriosIniciales = $contarCarpetasRespaldo();

        // Fallo en creación provocado intencionalmente (contraseña vacía)
        config(['menuonl.respaldos.password' => '']);
        try {
            app(RespaldoService::class)->crear();
        } catch (RuntimeException) {
        }
        $this->assertSame($directoriosIniciales, $contarCarpetasRespaldo(), 'No deben quedar carpetas huérfanas tras fallo de creación por contraseña.');

        // Restaurar contraseña válida
        config(['menuonl.respaldos.password' => self::PASSWORD]);

        // Fallo en creación provocado por SQLite dañado
        config(['database.connections.sqlite.database' => $this->raiz.'/inexistente.sqlite']);
        try {
            app(RespaldoService::class)->crear();
        } catch (RuntimeException) {
        }
        $this->assertSame($directoriosIniciales, $contarCarpetasRespaldo(), 'No deben quedar carpetas huérfanas tras fallo de copia SQLite.');

        // Restaurar base válida
        config(['database.connections.sqlite.database' => $this->sqlitePath]);

        // Fallo en verificación con archivo no válido
        Storage::disk('backups')->put('menuonl-20261018-030303.zip', 'no-es-un-zip');
        try {
            app(RespaldoService::class)->verificar('menuonl-20261018-030303.zip');
        } catch (RuntimeException) {
        }
        $this->assertSame($directoriosIniciales, $contarCarpetasRespaldo(), 'No deben quedar carpetas huérfanas tras fallo de verificación con zip inválido.');
    }

    /**
     * Test corrupt/truncated zips: 0-byte file, random noise, missing manifest, image count mismatch.
     */
    public function test_verificacion_detecta_archivos_corruptos_truncados_y_sin_manifest(): void
    {
        $service = app(RespaldoService::class);

        // A. Archivo de 0 bytes
        Storage::disk('backups')->put('menuonl-20261018-040401.zip', '');
        try {
            $service->verificar('menuonl-20261018-040401.zip');
            $this->fail('Se esperaba RuntimeException para archivo de 0 bytes.');
        } catch (RuntimeException $e) {
            $this->assertTrue(
                str_contains($e->getMessage(), 'no es un zip válido') || str_contains($e->getMessage(), 'manifest.json válido'),
                'Mensaje de excepción inesperado: '.$e->getMessage()
            );
        }

        // B. Ruido binario aleatorio
        Storage::disk('backups')->put('menuonl-20261018-040402.zip', random_bytes(256));
        try {
            $service->verificar('menuonl-20261018-040402.zip');
            $this->fail('Se esperaba RuntimeException para ruido binario.');
        } catch (RuntimeException $e) {
            $this->assertTrue(
                str_contains($e->getMessage(), 'no es un zip válido') || str_contains($e->getMessage(), 'manifest.json válido'),
                'Mensaje de excepción inesperado: '.$e->getMessage()
            );
        }

        // C. Zip válido pero sin manifest.json
        $this->crearZipManipulado('menuonl-20261018-040403.zip', [
            'database.sqlite' => (string) file_get_contents($this->sqlitePath),
        ]);
        try {
            $service->verificar('menuonl-20261018-040403.zip');
            $this->fail('Se esperaba RuntimeException para zip sin manifest.json.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('manifest.json válido', $e->getMessage());
        }

        // D. Discrepancia en conteo de imágenes (manifest indica 5 imágenes, el zip contiene 0)
        $this->crearZipManipulado('menuonl-20261018-040404.zip', [
            'manifest.json' => (string) json_encode([
                'creado_en' => '2026-10-18T00:00:00Z',
                'base_de_datos' => 'database.sqlite',
                'imagenes' => 5,
            ]),
            'database.sqlite' => (string) file_get_contents($this->sqlitePath),
        ]);
        try {
            $service->verificar('menuonl-20261018-040404.zip');
            $this->fail('Se esperaba RuntimeException para discrepancia de imágenes.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('no coinciden con el manifest', $e->getMessage());
        }
    }
}
