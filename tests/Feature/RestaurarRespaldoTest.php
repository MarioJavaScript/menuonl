<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;
use ZipArchive;

/**
 * menuonl:restaurar-respaldo con SQLite: restaura sin tocar lo activo, verifica antes de escribir y no imprime secretos.
 * La parte de MariaDB está en RestaurarRespaldoMariaDbTest.
 */
class RestaurarRespaldoTest extends TestCase
{
    use ArmaEscenarioDePanel;

    private const PASSWORD = 'clave-de-prueba-123';

    private string $raiz;

    private string $sqliteActiva;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();

        $this->raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'menuonl-restaurar-'.bin2hex(random_bytes(6));
        mkdir($this->raiz.'/storage/app/public/restaurantes/1/productos', 0777, true);
        file_put_contents($this->raiz.'/storage/app/public/restaurantes/1/productos/foto.webp', 'imagen-original');
        $this->app->useStoragePath($this->raiz.'/storage');

        $this->sqliteActiva = $this->raiz.'/activa.sqlite';
        $this->escribirFila($this->sqliteActiva, 'Café Ñandú', crear: true);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->sqliteActiva,
            'menuonl.respaldos.password' => self::PASSWORD,
            'menuonl.respaldos.disco' => 'backups',
        ]);
        Storage::fake('backups');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-20 10:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        $this->borrar($this->raiz);
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nombresInvalidos(): array
    {
        return [
            'con ruta relativa' => ['../../etc/passwd.zip'],
            'con barra' => ['carpeta/menuonl-20261020-100000.zip'],
            'otro prefijo' => ['otro-20261020-100000.zip'],
            'sin extensión' => ['menuonl-20261020-100000'],
            'inexistente' => ['menuonl-19990101-000000.zip'],
        ];
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

    private function escribirFila(string $archivo, string $nombre, bool $crear = false): void
    {
        $pdo = new PDO('sqlite:'.$archivo);

        if ($crear) {
            $pdo->exec('CREATE TABLE restaurantes (id INTEGER PRIMARY KEY, nombre TEXT)');
        }

        $pdo->prepare('INSERT INTO restaurantes (nombre) VALUES (?)')->execute([$nombre]);
    }

    /**
     * @return list<string>
     */
    private function nombresEnArchivo(string $archivo): array
    {
        return (new PDO('sqlite:'.$archivo))->query('SELECT nombre FROM restaurantes ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    }

    private function crearRespaldo(): string
    {
        $this->artisan('menuonl:backup')->assertExitCode(0);

        $archivos = Storage::disk('backups')->files();
        sort($archivos);

        return end($archivos);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function ejecutar(array $parametros): array
    {
        $codigo = Artisan::call('menuonl:restaurar-respaldo', $parametros + ['--no-interaction' => true]);

        return [$codigo, Artisan::output()];
    }

    private function destino(string $nombre = 'restaurada'): string
    {
        return $this->raiz.'/'.$nombre.'.sqlite';
    }

    public function test_restaura_un_respaldo_valido_en_un_archivo_nuevo_sin_tocar_la_base_activa(): void
    {
        $nombre = $this->crearRespaldo();
        $this->escribirFila($this->sqliteActiva, 'Creado después del respaldo');
        $hashActiva = hash_file('sha256', $this->sqliteActiva);

        [$codigo, $salida] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->destino(), '--confirmo' => true]);

        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('Restauración terminada', $salida);
        $this->assertSame(['Café Ñandú'], $this->nombresEnArchivo($this->destino()));
        $this->assertSame($hashActiva, hash_file('sha256', $this->sqliteActiva), 'La base activa no debe cambiar.');
    }

    public function test_sin_indicar_nombre_usa_el_mas_reciente_y_deja_las_imagenes_en_una_carpeta_nueva(): void
    {
        $this->crearRespaldo();
        $this->escribirFila($this->sqliteActiva, 'Segundo estado');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-21 10:00:00', 'UTC'));
        $reciente = $this->crearRespaldo();
        $this->assertSame('menuonl-20261021-100000.zip', $reciente);

        [$codigo, $salida] = $this->ejecutar(['--base-destino' => $this->destino(), '--confirmo' => true]);

        $this->assertSame(0, $codigo, $salida);
        $this->assertSame(['Café Ñandú', 'Segundo estado'], $this->nombresEnArchivo($this->destino()));

        $carpeta = $this->raiz.'/storage/app/restauraciones/menuonl-20261021-100000/restaurantes/1/productos/foto.webp';
        $this->assertSame('imagen-original', file_get_contents($carpeta));
    }

    public function test_un_nombre_concreto_restaura_ese_respaldo_y_no_el_mas_reciente(): void
    {
        $primero = $this->crearRespaldo();
        $this->escribirFila($this->sqliteActiva, 'Segundo estado');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-21 10:00:00', 'UTC'));
        $this->crearRespaldo();

        [$codigo] = $this->ejecutar(['nombre' => $primero, '--base-destino' => $this->destino(), '--confirmo' => true, '--sin-imagenes' => true]);

        $this->assertSame(0, $codigo);
        $this->assertSame(['Café Ñandú'], $this->nombresEnArchivo($this->destino()));
        $this->assertDirectoryDoesNotExist($this->raiz.'/storage/app/restauraciones');
    }

    public function test_sin_confirmacion_en_terminal_interactiva_cancela_y_no_crea_nada(): void
    {
        $nombre = $this->crearRespaldo();

        $this->artisan('menuonl:restaurar-respaldo', ['nombre' => $nombre, '--base-destino' => $this->destino()])
            ->expectsConfirmation('¿Continuar con la restauración?', 'no')
            ->expectsOutputToContain('Restauración cancelada')
            ->assertExitCode(1);

        $this->assertFileDoesNotExist($this->destino());
        $this->assertDirectoryDoesNotExist($this->raiz.'/storage/app/restauraciones');
    }

    public function test_sin_terminal_interactiva_exige_confirmo(): void
    {
        $nombre = $this->crearRespaldo();

        [$codigo, $salida] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->destino()]);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('--confirmo', $salida);
        $this->assertFileDoesNotExist($this->destino());
    }

    public function test_un_archivo_corrupto_falla_sin_tocar_la_base_ni_las_imagenes(): void
    {
        $nombre = $this->crearRespaldo();
        $disco = Storage::disk('backups');
        $bytes = $disco->get($nombre);
        $mitad = intdiv(strlen($bytes), 2);
        $disco->put($nombre, substr($bytes, 0, $mitad).strrev(substr($bytes, $mitad, 64)).substr($bytes, $mitad + 64));
        $hashActiva = hash_file('sha256', $this->sqliteActiva);

        [$codigo, $salida] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->destino(), '--confirmo' => true]);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('La restauración falló', $salida);
        $this->assertFileDoesNotExist($this->destino());
        $this->assertSame($hashActiva, hash_file('sha256', $this->sqliteActiva));
        $this->assertSame('imagen-original', file_get_contents($this->raiz.'/storage/app/public/restaurantes/1/productos/foto.webp'));
        $this->assertSame([], glob(sys_get_temp_dir().'/menuonl-respaldo-*') ?: [], 'No debe quedar basura temporal.');
    }

    public function test_un_archivo_que_no_es_zip_falla(): void
    {
        $nombre = 'menuonl-20261020-090000.zip';
        Storage::disk('backups')->put($nombre, 'esto no es un zip');

        [$codigo] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->destino(), '--confirmo' => true]);

        $this->assertSame(1, $codigo);
        $this->assertFileDoesNotExist($this->destino());
    }

    public function test_con_la_clave_equivocada_falla_sin_escribir_nada(): void
    {
        $nombre = $this->crearRespaldo();
        config(['menuonl.respaldos.password' => 'clave-equivocada']);

        [$codigo] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->destino(), '--confirmo' => true]);

        $this->assertSame(1, $codigo);
        $this->assertFileDoesNotExist($this->destino());
    }

    public function test_si_la_suma_de_verificacion_del_volcado_no_coincide_falla_sin_escribir_nada(): void
    {
        $nombre = $this->crearRespaldo();
        $disco = Storage::disk('backups');
        $local = $this->raiz.'/manipulado.zip';
        file_put_contents($local, $disco->get($nombre));

        $zip = new ZipArchive;
        $zip->open($local);
        $zip->setPassword(self::PASSWORD);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertArrayHasKey('sha256_base', $manifest);
        $manifest['sha256_base'] = str_repeat('0', 64);
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256);
        $zip->close();
        $disco->put($nombre, file_get_contents($local));

        [$codigo, $salida] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->destino(), '--confirmo' => true]);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('suma de verificación', $salida);
        $this->assertFileDoesNotExist($this->destino());
    }

    #[DataProvider('nombresInvalidos')]
    public function test_un_nombre_de_respaldo_invalido_o_inexistente_falla(string $nombre): void
    {
        [$codigo, $salida] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->destino(), '--confirmo' => true]);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('La restauración falló', $salida);
        $this->assertFileDoesNotExist($this->destino());
    }

    public function test_sin_respaldos_en_el_disco_falla_con_un_mensaje_claro(): void
    {
        [$codigo, $salida] = $this->ejecutar(['--base-destino' => $this->destino(), '--confirmo' => true]);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('No hay respaldos en el disco', $salida);
    }

    public function test_no_acepta_como_destino_la_base_activa_sin_la_opcion_explicita(): void
    {
        $nombre = $this->crearRespaldo();
        $this->escribirFila($this->sqliteActiva, 'Dato posterior');
        $hashActiva = hash_file('sha256', $this->sqliteActiva);

        [$codigo, $salida] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->sqliteActiva, '--confirmo' => true]);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('--sobrescribir-base-activa', $salida);
        $this->assertSame($hashActiva, hash_file('sha256', $this->sqliteActiva));
    }

    public function test_no_sobrescribe_un_archivo_de_destino_que_ya_existe(): void
    {
        $nombre = $this->crearRespaldo();
        file_put_contents($this->destino(), 'ya estaba');

        [$codigo, $salida] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->destino(), '--confirmo' => true]);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('ya existe', $salida);
        $this->assertSame('ya estaba', file_get_contents($this->destino()));
    }

    public function test_sobrescribir_la_base_activa_exige_el_nombre_exacto_y_conserva_la_anterior(): void
    {
        $nombre = $this->crearRespaldo();
        $this->escribirFila($this->sqliteActiva, 'Dato posterior al respaldo');

        $this->artisan('menuonl:restaurar-respaldo', ['nombre' => $nombre, '--sobrescribir-base-activa' => true, '--sin-imagenes' => true])
            ->expectsConfirmation('¿Continuar con la restauración?', 'yes')
            ->expectsQuestion('Vas a REEMPLAZAR la base activa. Escribe su nombre exacto para confirmar ('.$this->sqliteActiva.')', $this->sqliteActiva)
            ->assertExitCode(0);

        $this->assertSame(['Café Ñandú'], $this->nombresEnArchivo($this->sqliteActiva));

        $previas = glob($this->sqliteActiva.'.previa-*') ?: [];
        $this->assertCount(1, $previas);
        $this->assertSame(['Café Ñandú', 'Dato posterior al respaldo'], $this->nombresEnArchivo($previas[0]));
    }

    public function test_sobrescribir_con_el_nombre_equivocado_cancela_sin_cambios(): void
    {
        $nombre = $this->crearRespaldo();
        $this->escribirFila($this->sqliteActiva, 'Dato posterior');
        $hashActiva = hash_file('sha256', $this->sqliteActiva);

        $this->artisan('menuonl:restaurar-respaldo', ['nombre' => $nombre, '--sobrescribir-base-activa' => true])
            ->expectsConfirmation('¿Continuar con la restauración?', 'yes')
            ->expectsQuestion('Vas a REEMPLAZAR la base activa. Escribe su nombre exacto para confirmar ('.$this->sqliteActiva.')', 'otra-cosa')
            ->assertExitCode(1);

        $this->assertSame($hashActiva, hash_file('sha256', $this->sqliteActiva));
        $this->assertSame([], glob($this->sqliteActiva.'.previa-*') ?: []);
    }

    public function test_sin_terminal_sobrescribir_la_activa_exige_confirmo_base_exacto(): void
    {
        $nombre = $this->crearRespaldo();
        $this->escribirFila($this->sqliteActiva, 'Dato posterior');
        $hashActiva = hash_file('sha256', $this->sqliteActiva);

        [$sinNombre] = $this->ejecutar(['nombre' => $nombre, '--sobrescribir-base-activa' => true, '--confirmo' => true]);
        [$conOtro] = $this->ejecutar(['nombre' => $nombre, '--sobrescribir-base-activa' => true, '--confirmo' => true, '--confirmo-base' => 'otra']);

        $this->assertSame(1, $sinNombre);
        $this->assertSame(1, $conOtro);
        $this->assertSame($hashActiva, hash_file('sha256', $this->sqliteActiva));

        [$bien] = $this->ejecutar(['nombre' => $nombre, '--sobrescribir-base-activa' => true, '--confirmo' => true, '--confirmo-base' => $this->sqliteActiva, '--sin-imagenes' => true]);

        $this->assertSame(0, $bien);
        $this->assertSame(['Café Ñandú'], $this->nombresEnArchivo($this->sqliteActiva));
    }

    public function test_sobrescribir_base_activa_con_otro_destino_es_contradictorio(): void
    {
        $nombre = $this->crearRespaldo();

        [$codigo, $salida] = $this->ejecutar(['nombre' => $nombre, '--sobrescribir-base-activa' => true, '--base-destino' => $this->destino(), '--confirmo' => true]);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('solo se puede usar', $salida);
        $this->assertFileDoesNotExist($this->destino());
    }

    public function test_sobrescribir_imagenes_conserva_las_anteriores_y_sin_imagenes_no_las_toca(): void
    {
        $nombre = $this->crearRespaldo();
        $viva = $this->raiz.'/storage/app/public/restaurantes';
        file_put_contents($viva.'/1/productos/foto.webp', 'imagen-modificada-despues');
        file_put_contents($viva.'/1/productos/nueva.webp', 'solo-en-la-viva');

        [$sin] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->destino('a'), '--sobrescribir-imagenes' => true, '--sin-imagenes' => true, '--confirmo' => true]);
        $this->assertSame(0, $sin);
        $this->assertSame('imagen-modificada-despues', file_get_contents($viva.'/1/productos/foto.webp'));

        [$con, $salida] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->destino('b'), '--sobrescribir-imagenes' => true, '--confirmo' => true]);

        $this->assertSame(0, $con, $salida);
        $this->assertSame('imagen-original', file_get_contents($viva.'/1/productos/foto.webp'));
        $this->assertFileDoesNotExist($viva.'/1/productos/nueva.webp');

        $previas = glob($viva.'.previa-*') ?: [];
        $this->assertCount(1, $previas);
        $this->assertSame('solo-en-la-viva', file_get_contents($previas[0].'/1/productos/nueva.webp'));
    }

    public function test_si_la_carpeta_de_imagenes_de_destino_ya_tiene_archivos_no_se_sobrescribe_y_la_base_tampoco_cambia(): void
    {
        $nombre = $this->crearRespaldo();
        $carpeta = $this->raiz.'/imagenes-existentes';
        mkdir($carpeta);
        file_put_contents($carpeta.'/ocupada.txt', 'x');

        [$codigo, $salida] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->destino(), '--carpeta-imagenes' => $carpeta, '--confirmo' => true]);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('no está vacía', $salida);
        $this->assertFileDoesNotExist($this->destino(), 'Si las imágenes no se pueden dejar, la base tampoco se toca.');
        $this->assertSame('x', file_get_contents($carpeta.'/ocupada.txt'));
    }

    public function test_si_el_respaldo_es_de_otro_motor_falla_con_un_mensaje_claro(): void
    {
        $this->crearRespaldo();
        config([
            'database.default' => 'mariadb',
            'database.connections.mariadb.database' => 'menuonl',
        ]);

        [$codigo, $salida] = $this->ejecutar(['--base-destino' => 'menuonl_otra', '--confirmo' => true]);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('motores distintos', $salida);
    }

    public function test_ninguna_salida_ni_registro_imprime_secretos(): void
    {
        config([
            'menuonl.respaldos.restauracion.password' => 'clave-de-restauracion-secreta',
            'menuonl.respaldos.db_password' => 'clave-de-respaldo-secreta',
            'database.connections.sqlite.password' => 'clave-de-la-aplicacion-secreta',
        ]);
        $nombre = $this->crearRespaldo();
        Log::spy();

        $salidas = [];
        [, $salidas[]] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->destino('ok'), '--confirmo' => true]);
        [, $salidas[]] = $this->ejecutar(['nombre' => 'menuonl-19990101-000000.zip', '--base-destino' => $this->destino('mal'), '--confirmo' => true]);
        [, $salidas[]] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->sqliteActiva, '--confirmo' => true]);

        config(['menuonl.respaldos.password' => 'clave-equivocada']);
        [, $salidas[]] = $this->ejecutar(['nombre' => $nombre, '--base-destino' => $this->destino('clave'), '--confirmo' => true]);

        foreach ($salidas as $salida) {
            foreach ([self::PASSWORD, 'clave-equivocada', 'clave-de-restauracion-secreta', 'clave-de-respaldo-secreta', 'clave-de-la-aplicacion-secreta'] as $secreto) {
                $this->assertStringNotContainsString($secreto, $salida);
            }
        }

        Log::shouldHaveReceived('error')->withArgs(function (string $mensaje, array $contexto = []): bool {
            foreach ([self::PASSWORD, 'clave-equivocada'] as $secreto) {
                if (str_contains($mensaje.json_encode($contexto), $secreto)) {
                    return false;
                }
            }

            return true;
        });
    }
}
