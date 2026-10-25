<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * menuonl:restaurar-respaldo con MariaDB. El cliente mysql se simula con Process::fake: aquí se comprueba QUÉ se le pide
 * y en qué orden, no el comportamiento real de MariaDB (eso solo se confirma con un servidor real; ver docs/despliegue.md).
 */
class RestaurarRespaldoMariaDbTest extends TestCase
{
    private const PASSWORD = 'clave-de-prueba-123';

    private const NOMBRE = 'menuonl-20261020-100000.zip';

    private string $raiz;

    /**
     * @var list<string>
     */
    private array $consultas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'menuonl-restaurar-sql-'.bin2hex(random_bytes(6));
        mkdir($this->raiz.'/storage/app/public', 0777, true);
        $this->app->useStoragePath($this->raiz.'/storage');

        config([
            'database.default' => 'mariadb',
            'database.connections.mariadb.host' => 'db.interno',
            'database.connections.mariadb.database' => 'menuonl',
            'database.connections.mariadb.username' => 'app_usuario',
            'database.connections.mariadb.password' => 'clave-de-la-aplicacion',
            'menuonl.respaldos.password' => self::PASSWORD,
            'menuonl.respaldos.disco' => 'backups',
            'menuonl.respaldos.restauracion.usuario' => null,
            'menuonl.respaldos.restauracion.password' => null,
        ]);
        Storage::fake('backups');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-20 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
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

    private function subirRespaldo(string $sql = "CREATE TABLE `restaurantes` (id int);\nINSERT INTO `restaurantes` VALUES (1);\n", ?string $sha = null): void
    {
        $local = $this->raiz.'/armado.zip';
        $zip = new ZipArchive;
        $zip->open($local, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->setPassword(self::PASSWORD);
        $zip->addFromString('database.sql', $sql);
        $zip->addFromString('manifest.json', json_encode([
            'base_de_datos' => 'database.sql',
            'sha256_base' => $sha ?? hash('sha256', $sql),
            'imagenes' => 0,
        ]));
        $zip->setEncryptionName('database.sql', ZipArchive::EM_AES_256);
        $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256);
        $zip->close();

        Storage::disk('backups')->put(self::NOMBRE, file_get_contents($local));
        unlink($local);
    }

    /**
     * Simula el cliente mysql: responde según la consulta recibida.
     *
     * @param  array<string, string>  $respuestas  fragmento de consulta => salida
     */
    private function simularCliente(array $respuestas = [], ?string $fallaSiContiene = null, string $errorDelCliente = 'ERROR 1045 Access denied'): void
    {
        $this->consultas = [];

        Process::fake(function (PendingProcess $proceso) use ($respuestas, $fallaSiContiene, $errorDelCliente): ProcessResult {
            $comando = is_array($proceso->command) ? $proceso->command : [$proceso->command];
            $consulta = (string) end($comando);
            $this->consultas[] = $consulta;

            if ($fallaSiContiene !== null && str_contains(implode(' ', $comando), $fallaSiContiene)) {
                return Process::result(output: '', errorOutput: $errorDelCliente, exitCode: 1);
            }

            foreach ($respuestas as $fragmento => $salida) {
                if (str_contains($consulta, $fragmento)) {
                    return Process::result(output: $salida);
                }
            }

            return Process::result(output: '');
        });
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function ejecutar(array $parametros): array
    {
        $codigo = Artisan::call('menuonl:restaurar-respaldo', $parametros + ['--no-interaction' => true, '--confirmo' => true, '--sin-imagenes' => true]);

        return [$codigo, Artisan::output()];
    }

    private function consultasQueContienen(string $fragmento): int
    {
        return count(array_filter($this->consultas, fn (string $c): bool => str_contains($c, $fragmento)));
    }

    public function test_restaura_en_una_base_nueva_sin_tocar_la_activa(): void
    {
        $this->subirRespaldo();
        $this->simularCliente(['information_schema.SCHEMATA' => "0\n", 'information_schema.TABLES' => "restaurantes\n"]);

        [$codigo, $salida] = $this->ejecutar(['nombre' => self::NOMBRE, '--base-destino' => 'menuonl_copia']);

        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('la base activa no se tocó', $salida);

        Process::assertRan(fn ($p): bool => in_array('--database=menuonl_copia', (array) $p->command, true)
            && ($p->environment['MYSQL_PWD'] ?? null) === 'clave-de-la-aplicacion'
            && in_array('--user=app_usuario', (array) $p->command, true)
            && ! str_contains(implode(' ', (array) $p->command), 'clave-de-la-aplicacion'));
        $this->assertSame(1, $this->consultasQueContienen('CREATE DATABASE IF NOT EXISTS `menuonl_copia`'));
        $this->assertSame(0, $this->consultasQueContienen('RENAME TABLE'));
        $this->assertSame(0, $this->consultasQueContienen('DROP DATABASE'));
        Process::assertNotRan(fn ($p): bool => in_array('--database=menuonl', (array) $p->command, true));
    }

    public function test_si_la_importacion_falla_borra_la_base_que_creo_y_oculta_la_clave(): void
    {
        $this->subirRespaldo();
        $this->simularCliente(
            ['information_schema.SCHEMATA' => "0\n"],
            fallaSiContiene: '--database=menuonl_copia',
            errorDelCliente: 'ERROR en la linea 3 con clave-de-la-aplicacion',
        );

        [$codigo, $salida] = $this->ejecutar(['nombre' => self::NOMBRE, '--base-destino' => 'menuonl_copia']);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('La restauración falló', $salida);
        $this->assertStringContainsString('[oculto]', $salida);
        $this->assertStringNotContainsString('clave-de-la-aplicacion', $salida);
        $this->assertSame(1, $this->consultasQueContienen('DROP DATABASE IF EXISTS `menuonl_copia`'));
    }

    public function test_no_restaura_sobre_una_base_que_ya_tiene_tablas(): void
    {
        $this->subirRespaldo();
        $this->simularCliente(['information_schema.SCHEMATA' => "1\n", 'information_schema.TABLES' => "usuarios\n"]);

        [$codigo, $salida] = $this->ejecutar(['nombre' => self::NOMBRE, '--base-destino' => 'menuonl_ocupada']);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('ya tiene tablas', $salida);
        $this->assertSame(0, $this->consultasQueContienen('CREATE DATABASE'));
        $this->assertSame(0, $this->consultasQueContienen('DROP DATABASE'));
    }

    public function test_rechaza_la_base_activa_como_destino_sin_la_opcion_explicita_y_sin_llamar_al_cliente(): void
    {
        $this->subirRespaldo();
        $this->simularCliente();

        [$codigo, $salida] = $this->ejecutar(['nombre' => self::NOMBRE, '--base-destino' => 'MENUONL']);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('--sobrescribir-base-activa', $salida);
        Process::assertNothingRan();
    }

    public function test_rechaza_nombres_de_base_con_caracteres_peligrosos(): void
    {
        $this->subirRespaldo();
        $this->simularCliente();

        [$codigo] = $this->ejecutar(['nombre' => self::NOMBRE, '--base-destino' => 'copia`; DROP DATABASE menuonl; --']);

        $this->assertSame(1, $codigo);
        Process::assertNothingRan();
    }

    public function test_un_volcado_con_use_o_drop_database_no_se_importa(): void
    {
        $this->subirRespaldo("CREATE TABLE `a` (id int);\nUSE `otra_base`;\n");
        $this->simularCliente(['information_schema.SCHEMATA' => "0\n"]);

        [$codigo, $salida] = $this->ejecutar(['nombre' => self::NOMBRE, '--base-destino' => 'menuonl_copia']);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('USE/CREATE/DROP DATABASE', $salida);
        Process::assertNothingRan();
    }

    public function test_un_volcado_corrupto_falla_sin_llamar_al_cliente(): void
    {
        $this->subirRespaldo(sha: str_repeat('a', 64));
        $this->simularCliente();

        [$codigo, $salida] = $this->ejecutar(['nombre' => self::NOMBRE, '--base-destino' => 'menuonl_copia']);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('suma de verificación', $salida);
        Process::assertNothingRan();
    }

    public function test_sobrescribir_la_activa_importa_a_una_temporal_y_cambia_todo_con_un_solo_rename(): void
    {
        $this->subirRespaldo();
        $this->simularCliente([
            'COUNT(*) FROM information_schema.VIEWS' => "0\n",
            'information_schema.TABLES' => "restaurantes\n",
        ]);

        [$codigo, $salida] = $this->ejecutar(['nombre' => self::NOMBRE, '--sobrescribir-base-activa' => true, '--confirmo-base' => 'menuonl']);

        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('menuonl_previa_20261020120000', $salida);

        $renames = array_values(array_filter($this->consultas, fn (string $c): bool => str_starts_with($c, 'RENAME TABLE')));
        $this->assertCount(1, $renames, 'El cambio debe ser una única sentencia atómica.');
        $this->assertStringContainsString('`menuonl`.`restaurantes` TO `menuonl_previa_20261020120000`.`restaurantes`', $renames[0]);
        $this->assertMatchesRegularExpression('/`menuonl_rest_[0-9a-f]{8}`\.`restaurantes` TO `menuonl`\.`restaurantes`/', $renames[0]);

        $this->assertSame(1, $this->consultasQueContienen('DROP DATABASE IF EXISTS `menuonl_rest_'), 'La base temporal se borra al terminar.');
        $this->assertSame(0, $this->consultasQueContienen('DROP DATABASE IF EXISTS `menuonl_previa_'), 'La base anterior se conserva.');

        $posRename = array_search($renames[0], $this->consultas, true);
        $posImport = null;
        foreach ($this->consultas as $i => $c) {
            if (str_starts_with($c, 'CREATE DATABASE IF NOT EXISTS `menuonl_rest_')) {
                $posImport = $i;
            }
        }
        $this->assertLessThan($posRename, $posImport);
    }

    public function test_sobrescribir_la_activa_se_niega_si_hay_vistas_disparadores_o_rutinas(): void
    {
        $this->subirRespaldo();
        $this->simularCliente([
            'COUNT(*) FROM information_schema.VIEWS' => "2\n",
            'information_schema.TABLES' => "restaurantes\n",
        ]);

        [$codigo, $salida] = $this->ejecutar(['nombre' => self::NOMBRE, '--sobrescribir-base-activa' => true, '--confirmo-base' => 'menuonl']);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('vistas, disparadores o rutinas', $salida);
        $this->assertSame(0, $this->consultasQueContienen('RENAME TABLE'));
        $this->assertSame(1, $this->consultasQueContienen('DROP DATABASE IF EXISTS `menuonl_rest_'));
        $this->assertSame(0, $this->consultasQueContienen('DROP DATABASE IF EXISTS `menuonl`'));
    }

    public function test_si_el_rename_falla_la_activa_queda_y_se_limpian_las_bases_auxiliares(): void
    {
        $this->subirRespaldo();
        $this->simularCliente([
            'COUNT(*) FROM information_schema.VIEWS' => "0\n",
            'information_schema.TABLES' => "restaurantes\n",
        ], fallaSiContiene: 'RENAME TABLE');

        [$codigo, $salida] = $this->ejecutar(['nombre' => self::NOMBRE, '--sobrescribir-base-activa' => true, '--confirmo-base' => 'menuonl']);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('lo que estaba activo sigue como estaba', $salida);
        $this->assertSame(1, $this->consultasQueContienen('DROP DATABASE IF EXISTS `menuonl_rest_'));
        $this->assertSame(1, $this->consultasQueContienen('DROP DATABASE IF EXISTS `menuonl_previa_'));
        $this->assertSame(0, $this->consultasQueContienen('DROP DATABASE IF EXISTS `menuonl`'));
    }

    public function test_usa_el_usuario_de_restauracion_si_esta_configurado(): void
    {
        config([
            'menuonl.respaldos.restauracion.usuario' => 'restaurador',
            'menuonl.respaldos.restauracion.password' => 'clave-de-restauracion',
        ]);
        $this->subirRespaldo();
        $this->simularCliente(['information_schema.SCHEMATA' => "0\n", 'information_schema.TABLES' => "restaurantes\n"]);

        [$codigo, $salida] = $this->ejecutar(['nombre' => self::NOMBRE, '--base-destino' => 'menuonl_copia']);

        $this->assertSame(0, $codigo, $salida);
        Process::assertRan(fn ($p): bool => in_array('--user=restaurador', (array) $p->command, true)
            && ($p->environment['MYSQL_PWD'] ?? null) === 'clave-de-restauracion');
        Process::assertNotRan(fn ($p): bool => in_array('--user=app_usuario', (array) $p->command, true));
        $this->assertStringNotContainsString('clave-de-restauracion', $salida);
        $this->assertStringNotContainsString('clave-de-la-aplicacion', $salida);
    }

    public function test_si_el_respaldo_es_de_sqlite_y_la_base_activa_es_mariadb_falla_con_un_mensaje_claro(): void
    {
        $local = $this->raiz.'/base.sqlite';
        $pdo = new \PDO('sqlite:'.$local);
        $pdo->exec('CREATE TABLE restaurantes (id INTEGER PRIMARY KEY)');
        $pdo = null;

        $zipLocal = $this->raiz.'/sqlite.zip';
        $zip = new ZipArchive;
        $zip->open($zipLocal, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->setPassword(self::PASSWORD);
        $zip->addFile($local, 'database.sqlite');
        $zip->addFromString('manifest.json', json_encode(['base_de_datos' => 'database.sqlite', 'sha256_base' => hash_file('sha256', $local), 'imagenes' => 0]));
        $zip->setEncryptionName('database.sqlite', ZipArchive::EM_AES_256);
        $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256);
        $zip->close();
        Storage::disk('backups')->put(self::NOMBRE, file_get_contents($zipLocal));
        $this->simularCliente();

        [$codigo, $salida] = $this->ejecutar(['nombre' => self::NOMBRE, '--base-destino' => 'menuonl_copia']);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('motores distintos', $salida);
        Process::assertNothingRan();
    }
}
