<?php

namespace Tests\Feature;

use App\Console\Commands\RespaldarMenuonl;
use App\Console\Commands\VerificarRespaldoMenuonl;
use App\Services\RespaldoService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Adversarial stress and security harness for RespaldoService and backup Artisan commands.
 */
class RespaldoServiceAdversarialChallengerTest extends TestCase
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
     * Helper to create a crafted zip archive and place it in the fake backup disk.
     *
     * @param  array<string, string>  $entries  Entry name => file content
     */
    private function createCraftedBackupZip(string $backupFileName, array $entries, ?string $password = self::PASSWORD): void
    {
        $tempZip = sys_get_temp_dir().DIRECTORY_SEPARATOR.'temp_craft_'.bin2hex(random_bytes(6)).'.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        if ($password !== null) {
            $zip->setPassword($password);
        }

        foreach ($entries as $entryName => $content) {
            $zip->addFromString($entryName, $content);
            if ($password !== null) {
                $zip->setEncryptionName($entryName, ZipArchive::EM_AES_256);
            }
        }

        $zip->close();

        Storage::disk('backups')->put($backupFileName, file_get_contents($tempZip));
        unlink($tempZip);
    }

    /**
     * Test that calling verificar() with Zip Slip entries throws RuntimeException
     * and NEVER writes files outside the temporary directory.
     */
    public function test_verificar_rejects_zip_slip_and_never_escapes(): void
    {
        $service = app(RespaldoService::class);
        $backupName = 'menuonl-20261018-010000.zip';

        $dangerousEntries = [
            '../../evil.txt' => 'exploit_content',
            'manifest.json' => (string) json_encode(['base_de_datos' => 'database.sqlite', 'imagenes' => 0]),
            'database.sqlite' => (string) file_get_contents($this->sqlitePath),
        ];

        $this->createCraftedBackupZip($backupName, $dangerousEntries);

        try {
            $service->verificar($backupName);
            $this->fail('RespaldoService::verificar() should have thrown a RuntimeException for Zip Slip payload.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Ruta no autorizada', $e->getMessage());
        }

        // Verify the exploit file was never extracted anywhere in sys_get_temp_dir() or parent
        $this->assertFileDoesNotExist(sys_get_temp_dir().DIRECTORY_SEPARATOR.'evil.txt');
        $this->assertFileDoesNotExist(dirname(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'evil.txt');

        // Verify no leftover leaked extraction directories were left in sys_get_temp_dir()
        $tempFilesAfter = scandir(sys_get_temp_dir());
        $leakedDirs = array_filter($tempFilesAfter, function (string $f): bool {
            return str_starts_with($f, 'menuonl-respaldo-');
        });
        $this->assertEmpty($leakedDirs, 'Temporary restoration directory must be cleaned up in finally block.');
    }

    /**
     * Test various Zip Slip path traversal vectors (leading slash, drive letters, backslashes, mixed separators).
     */
    public function test_verificar_rejects_all_path_traversal_variations(): void
    {
        $service = app(RespaldoService::class);
        $traversalVectors = [
            'restaurantes/../../evil.txt',
            'restaurantes/subdir/../../../evil.txt',
            'restaurantes/..\evil.txt',
            'restaurantes\..\..\evil.txt',
            'restaurantes/./../../evil.txt',
            '/etc/shadow',
            '\\Windows\\System32\\calc.exe',
            '\\\\server\\share\\evil.txt',
            'C:evil.txt',
            'C:\\evil.txt',
            'D:/evil.txt',
        ];

        $counter = 1;
        foreach ($traversalVectors as $vector) {
            $backupName = sprintf('menuonl-20261018-0200%02d.zip', $counter++);

            $entries = [
                $vector => 'malicious_payload',
                'manifest.json' => (string) json_encode(['base_de_datos' => 'database.sqlite', 'imagenes' => 0]),
                'database.sqlite' => (string) file_get_contents($this->sqlitePath),
            ];

            $this->createCraftedBackupZip($backupName, $entries);

            try {
                $service->verificar($backupName);
                $this->fail("RespaldoService::verificar() did not reject traversal vector: [{$vector}]");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Ruta no autorizada', $e->getMessage());
            }
        }
    }

    /**
     * Test that archives containing unwhitelisted entries (e.g. .php shell or arbitrary files) are rejected.
     */
    public function test_verificar_rejects_unwhitelisted_entries(): void
    {
        $service = app(RespaldoService::class);
        $backupName = 'menuonl-20261018-030000.zip';

        $entries = [
            'unauthorized_script.php' => '<?php echo "pwned"; ?>',
            'manifest.json' => (string) json_encode(['base_de_datos' => 'database.sqlite', 'imagenes' => 0]),
            'database.sqlite' => (string) file_get_contents($this->sqlitePath),
        ];

        $this->createCraftedBackupZip($backupName, $entries);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Entrada no reconocida en el respaldo: unauthorized_script.php');

        $service->verificar($backupName);
    }

    /**
     * Test that invalid backup names are immediately rejected with RuntimeException before disk access.
     */
    public function test_verificar_rejects_invalid_backup_names(): void
    {
        $service = app(RespaldoService::class);

        $invalidNames = [
            '../evil.zip',
            '../../etc/passwd',
            'evil.zip',
            'menuonl-123.zip',
            'menuonl-20261018-010203.tar.gz',
            'menuonl-20261018-010203.zip/extra',
            'menuonl-20261018-010203.zip\\extra',
            'subdir/menuonl-20261018-010203.zip',
            'menuonl-20261018-010203.zip; id',
            'menuonl-abcdefgh-123456.zip',
        ];

        foreach ($invalidNames as $invalidName) {
            try {
                $service->verificar($invalidName);
                $this->fail("RespaldoService::verificar() did not reject invalid filename: [{$invalidName}]");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Nombre de respaldo inválido o con formato no permitido', $e->getMessage());
            }
        }
    }

    /**
     * Test that corrupted or empty zip files throw a clean RuntimeException.
     */
    public function test_verificar_rejects_corrupted_or_empty_zip(): void
    {
        $service = app(RespaldoService::class);
        $backupName = 'menuonl-20261018-040000.zip';

        Storage::disk('backups')->put($backupName, 'not-a-valid-zip-content');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no es un zip válido');

        $service->verificar($backupName);
    }

    /**
     * Test that verifying with the wrong decryption password throws RuntimeException.
     */
    public function test_verificar_rejects_wrong_password(): void
    {
        $service = app(RespaldoService::class);
        $backupName = 'menuonl-20261018-050000.zip';

        $entries = [
            'manifest.json' => (string) json_encode(['base_de_datos' => 'database.sqlite', 'imagenes' => 0]),
            'database.sqlite' => (string) file_get_contents($this->sqlitePath),
        ];

        $this->createCraftedBackupZip($backupName, $entries, 'secret-password-different');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo descifrar el respaldo: contraseña incorrecta o archivo dañado.');

        $service->verificar($backupName);
    }

    /**
     * Test that verifying a backup with a corrupted SQLite database throws RuntimeException.
     */
    public function test_verificar_rejects_corrupted_sqlite_database(): void
    {
        $service = app(RespaldoService::class);
        $backupName = 'menuonl-20261018-060000.zip';

        $entries = [
            'manifest.json' => (string) json_encode(['base_de_datos' => 'database.sqlite', 'imagenes' => 0]),
            'database.sqlite' => 'corrupted_content_not_sqlite',
        ];

        $this->createCraftedBackupZip($backupName, $entries);

        $this->expectException(\Throwable::class);

        $service->verificar($backupName);
    }

    /**
     * Test that verifying a backup with mismatched image count throws RuntimeException.
     */
    public function test_verificar_rejects_mismatched_image_count_in_manifest(): void
    {
        $service = app(RespaldoService::class);
        $backupName = 'menuonl-20261018-070000.zip';

        $entries = [
            'manifest.json' => (string) json_encode(['base_de_datos' => 'database.sqlite', 'imagenes' => 99]),
            'database.sqlite' => (string) file_get_contents($this->sqlitePath),
            'restaurantes/1/foto.webp' => 'fake_image',
        ];

        $this->createCraftedBackupZip($backupName, $entries);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Las imágenes restauradas (1) no coinciden con el manifest.');

        $service->verificar($backupName);
    }

    /**
     * Test that Artisan command aliases menuonl:respaldar and menuonl:verificar-respaldo
     * are properly registered and resolve to their respective Command classes.
     */
    public function test_artisan_backup_command_aliases_resolve_correctly(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('menuonl:respaldar', $commands);
        $this->assertInstanceOf(RespaldarMenuonl::class, $commands['menuonl:respaldar']);

        $this->assertArrayHasKey('menuonl:verificar-respaldo', $commands);
        $this->assertInstanceOf(VerificarRespaldoMenuonl::class, $commands['menuonl:verificar-respaldo']);
    }

    /**
     * Test calling menuonl:respaldar and menuonl:verificar-respaldo through Artisan CLI.
     */
    public function test_artisan_commands_execute_successfully(): void
    {
        // 1. Create a backup via the Spanish alias
        $this->artisan('menuonl:respaldar')
            ->expectsOutputToContain('Iniciando respaldo de MENUONL')
            ->expectsOutputToContain('subido')
            ->assertExitCode(0);

        $respaldos = Storage::disk('backups')->files();
        $this->assertCount(1, $respaldos);

        // 2. Verify the backup via the Spanish alias
        $this->artisan('menuonl:verificar-respaldo')
            ->expectsOutputToContain('verificado: se descifra y se puede restaurar')
            ->assertExitCode(0);

        // 3. Verify specific backup by name
        $this->artisan('menuonl:verificar-respaldo', ['archivo' => $respaldos[0]])
            ->expectsOutputToContain('verificado: se descifra y se puede restaurar')
            ->assertExitCode(0);

        // 4. Verify invalid name via Artisan command fails with exit code 1
        $this->artisan('menuonl:verificar-respaldo', ['archivo' => '../malicious.zip'])
            ->expectsOutputToContain('La verificación falló')
            ->assertExitCode(1);
    }
}
