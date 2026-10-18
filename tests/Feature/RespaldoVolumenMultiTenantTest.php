<?php

namespace Tests\Feature;

use App\Services\RespaldoService;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;
use ZipArchive;

class RespaldoVolumenMultiTenantTest extends TestCase
{
    private const PASSWORD = 'clave-volumen-multitenant-2026';

    private string $raiz;

    private string $sqlitePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'menuonl-vol-backup-'.bin2hex(random_bytes(6));
        mkdir($this->raiz.'/storage', 0777, true);
        $this->app->useStoragePath($this->raiz.'/storage');

        $this->sqlitePath = $this->raiz.'/volumen.sqlite';
        $pdo = new PDO('sqlite:'.$this->sqlitePath);
        $pdo->exec('
            CREATE TABLE restaurantes (id INTEGER PRIMARY KEY, slug TEXT, nombre TEXT);
            CREATE TABLE categorias (id INTEGER PRIMARY KEY, restaurante_id INTEGER, nombre TEXT);
            CREATE TABLE productos (id INTEGER PRIMARY KEY, restaurante_id INTEGER, categoria_id INTEGER, nombre TEXT, precio INTEGER);
            CREATE TABLE producto_variantes (id INTEGER PRIMARY KEY, producto_id INTEGER, nombre TEXT, precio INTEGER);
        ');

        $pdo->beginTransaction();
        $productoId = 1;
        $varianteId = 1;

        for ($r = 1; $r <= 3; $r++) {
            $pdo->exec("INSERT INTO restaurantes (id, slug, nombre) VALUES ({$r}, 'resto-{$r}', 'Restaurante Volumen {$r}')");

            for ($c = 1; $c <= 5; $c++) {
                $catId = (($r - 1) * 5) + $c;
                $pdo->exec("INSERT INTO categorias (id, restaurante_id, nombre) VALUES ({$catId}, {$r}, 'Categoría {$c}')");

                for ($p = 1; $p <= 10; $p++) {
                    $pdo->exec("INSERT INTO productos (id, restaurante_id, categoria_id, nombre, precio) VALUES ({$productoId}, {$r}, {$catId}, 'Platillo {$productoId}', 12500)");

                    if ($varianteId <= 60) {
                        $pdo->exec("INSERT INTO producto_variantes (id, producto_id, nombre, precio) VALUES ({$varianteId}, {$productoId}, 'Variante {$varianteId}', 15000)");
                        $varianteId++;
                    }

                    $productoId++;
                }
            }

            // Crear 10 imágenes WebP simuladas por cada uno de los 3 restaurantes (30 imágenes en total)
            $dirProductos = $this->raiz."/storage/app/public/restaurantes/{$r}/productos";
            mkdir($dirProductos, 0777, true);

            for ($img = 1; $img <= 10; $img++) {
                file_put_contents(
                    "{$dirProductos}/plato-{$r}-{$img}.webp",
                    "RIFF-WEBP-SIMULADO-RESTAURANTE-{$r}-IMAGEN-{$img}-".str_repeat('X', 1024)
                );
            }
        }

        // Archivo .env señuelo que jamás debe incluirse en el respaldo
        file_put_contents($this->raiz.'/storage/app/public/restaurantes/.env.backup', 'APP_KEY=secreto_prohibido');

        $pdo->commit();
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

    public function test_respaldo_y_verificacion_masiva_con_3_restaurantes_150_productos_60_variantes_y_30_imagenes_dentro_del_presupuesto_de_memoria(): void
    {
        $memoriaInicial = memory_get_usage(true);

        $servicio = app(RespaldoService::class);
        $creado = $servicio->crear();

        $this->assertSame(30, $creado['archivos'], 'El respaldo debe empaquetar exactamente las 30 imágenes WebP (excluyendo .env.backup).');
        $this->assertGreaterThan(0, $creado['bytes']);

        $verificado = $servicio->verificar($creado['nombre']);
        $this->assertSame($creado['nombre'], $verificado['nombre']);
        $this->assertSame(30, $verificado['archivos']);
        $this->assertSame(4, $verificado['tablas']);

        $deltaMemoriaMb = (memory_get_peak_usage(true) - $memoriaInicial) / (1024 * 1024);
        $this->assertLessThan(
            128,
            $deltaMemoriaMb,
            "El consumo pico adicional de memoria ({$deltaMemoriaMb} MB) debe mantenerse por debajo de 128 MB."
        );

        // Confirmar que ningún archivo .env fue incluido en el zip cifrado
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('backups')->path($creado['nombre'])));
        $zip->setPassword(self::PASSWORD);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombreEntrada = (string) $zip->getNameIndex($i);
            $this->assertStringNotContainsString('.env', $nombreEntrada);
        }

        $zip->close();
    }
}
