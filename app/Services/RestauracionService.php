<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Restaura la base y las imágenes de un respaldo ya descifrado y verificado por RespaldoService::prepararRestauracion().
 *
 * Reglas de seguridad:
 * - Nada se escribe en la base activa ni en las imágenes en uso salvo que se pida de forma explícita.
 * - La base se importa SIEMPRE primero a una base temporal (o archivo temporal). Solo si la importación termina bien se
 *   publica: en SQLite con un `rename` atómico; en MariaDB con un único `RENAME TABLE` (atómico) que además aparta la
 *   base anterior en otra base con fecha, para poder volver atrás.
 * - Las credenciales viajan por entorno (MYSQL_PWD), nunca en la línea de comandos, y los mensajes de error las ocultan.
 */
class RestauracionService
{
    private const PATRON_NOMBRE_BASE = '/^[A-Za-z0-9_]{1,64}$/';

    /**
     * 'sqlite' si la base activa es un archivo SQLite; 'sql' si es MariaDB/MySQL.
     *
     * @throws RuntimeException
     */
    public function motorActivo(): string
    {
        $driver = (string) config('database.connections.'.config('database.default').'.driver');

        return match ($driver) {
            'sqlite' => 'sqlite',
            'mysql', 'mariadb' => 'sql',
            default => throw new RuntimeException("Driver de base de datos no soportado para restaurar: {$driver}."),
        };
    }

    /**
     * Nombre de la base activa (MariaDB) o ruta de su archivo (SQLite).
     */
    public function baseActiva(): string
    {
        return (string) config('database.connections.'.config('database.default').'.database');
    }

    /**
     * Destino por defecto: una base/archivo nuevo, distinto del activo.
     */
    public function destinoPorDefecto(string $nombreRespaldo): string
    {
        $sello = CarbonImmutable::now('UTC')->format('YmdHis');

        if ($this->motorActivo() === 'sqlite') {
            return storage_path('app/restauraciones/'.basename($nombreRespaldo, '.zip').'.sqlite');
        }

        return substr($this->baseActiva(), 0, 64 - 20).'_restaurada_'.$sello;
    }

    public function esLaBaseActiva(string $destino): bool
    {
        $activa = $this->baseActiva();

        return $this->motorActivo() === 'sqlite'
            ? $this->rutaNormalizada($destino) === $this->rutaNormalizada($activa)
            : strcasecmp($destino, $activa) === 0;
    }

    /**
     * Revisa el destino antes de descargar nada.
     *
     * @throws RuntimeException
     */
    public function validarDestino(string $destino, bool $sobrescribirActiva): void
    {
        $esActiva = $this->esLaBaseActiva($destino);

        if ($esActiva && ! $sobrescribirActiva) {
            throw new RuntimeException('El destino es la base ACTIVA. Elige otra con --base-destino, o usa --sobrescribir-base-activa si de verdad quieres reemplazarla.');
        }

        if (! $esActiva && $sobrescribirActiva) {
            throw new RuntimeException('--sobrescribir-base-activa solo se puede usar sin --base-destino o con la base activa como destino.');
        }

        if ($this->motorActivo() === 'sqlite') {
            if ($sobrescribirActiva && ($this->baseActiva() === ':memory:' || ! is_file($this->baseActiva()))) {
                throw new RuntimeException('La base SQLite activa no es un archivo que se pueda reemplazar.');
            }

            if (! $esActiva && file_exists($destino)) {
                throw new RuntimeException('El archivo de destino ya existe; no se sobrescribe. Elige otro con --base-destino.');
            }

            return;
        }

        if (preg_match(self::PATRON_NOMBRE_BASE, $destino) !== 1) {
            throw new RuntimeException('El nombre de la base de destino solo puede llevar letras, números y guion bajo (máximo 64).');
        }
    }

    /**
     * Restaura el volcado en el destino y devuelve un resumen sin datos secretos.
     *
     * @param  array{motor: string, base: string, nombre: string}  $preparado
     *
     * @throws RuntimeException
     */
    public function restaurarBase(array $preparado, string $destino, bool $sobrescribirActiva): string
    {
        if ($preparado['motor'] !== $this->motorActivo()) {
            throw new RuntimeException('El respaldo es de '.($preparado['motor'] === 'sqlite' ? 'SQLite' : 'MariaDB/MySQL').' y la base activa es de '.($this->motorActivo() === 'sqlite' ? 'SQLite' : 'MariaDB/MySQL').'. No se puede restaurar entre motores distintos.');
        }

        $this->validarDestino($destino, $sobrescribirActiva);

        return $this->motorActivo() === 'sqlite'
            ? $this->restaurarSqlite($preparado['base'], $destino, $sobrescribirActiva)
            : $this->restaurarMysql($preparado['base'], $destino, $sobrescribirActiva);
    }

    private function restaurarSqlite(string $volcado, string $destino, bool $sobrescribirActiva): string
    {
        $carpeta = dirname($destino);

        if (! is_dir($carpeta) && ! mkdir($carpeta, 0750, true) && ! is_dir($carpeta)) {
            throw new RuntimeException('No se pudo crear la carpeta de destino.');
        }

        $previa = null;

        // Se copia junto al destino (mismo disco) para que el rename final sea atómico.
        $temporal = $destino.'.restaurando-'.bin2hex(random_bytes(4));

        if (! copy($volcado, $temporal)) {
            throw new RuntimeException('No se pudo copiar el volcado al destino.');
        }

        try {
            $pdo = new \PDO('sqlite:'.$temporal);
            $integridad = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $pdo = null;

            if ($integridad !== 'ok') {
                throw new RuntimeException('La copia restaurada no pasó la comprobación de integridad.');
            }

            if ($sobrescribirActiva) {
                $previa = $destino.'.previa-'.CarbonImmutable::now('UTC')->format('YmdHis');

                if (! copy($destino, $previa)) {
                    throw new RuntimeException('No se pudo guardar una copia de la base anterior; no se reemplazó nada.');
                }
            }

            if (! rename($temporal, $destino)) {
                throw new RuntimeException('No se pudo colocar la base restaurada.');
            }
        } catch (\Throwable $e) {
            @unlink($temporal);

            throw $e;
        }

        return $previa !== null
            ? 'Base SQLite activa reemplazada; la anterior quedó en '.basename($previa).'.'
            : 'Base SQLite restaurada en '.$destino.'.';
    }

    private function restaurarMysql(string $volcado, string $destino, bool $sobrescribirActiva): string
    {
        $this->rechazarSentenciasPeligrosas($volcado);

        if (! $sobrescribirActiva) {
            return $this->restaurarEnBaseNueva($volcado, $destino);
        }

        $activa = $this->baseActiva();
        $temporal = substr($activa, 0, 64 - 15).'_rest_'.bin2hex(random_bytes(4));
        $previa = substr($activa, 0, 64 - 22).'_previa_'.CarbonImmutable::now('UTC')->format('YmdHis');

        $this->crearBase($temporal);

        try {
            $this->importar($volcado, $temporal);
            $this->exigirSoloTablas($temporal);
            $this->exigirSoloTablas($activa);

            $tablasNuevas = $this->tablas($temporal);
            $tablasActuales = $this->tablas($activa);

            if ($tablasNuevas === []) {
                throw new RuntimeException('El volcado no creó ninguna tabla; no se reemplazó nada.');
            }

            $this->crearBase($previa);

            $movimientos = [];
            foreach ($tablasActuales as $tabla) {
                $movimientos[] = "`{$activa}`.`{$tabla}` TO `{$previa}`.`{$tabla}`";
            }
            foreach ($tablasNuevas as $tabla) {
                $movimientos[] = "`{$temporal}`.`{$tabla}` TO `{$activa}`.`{$tabla}`";
            }

            // Una sola sentencia: MariaDB la ejecuta de forma atómica; o se hace todo o no se hace nada.
            $this->ejecutar('RENAME TABLE '.implode(', ', $movimientos));
        } catch (\Throwable $e) {
            $this->soltarBaseSiExiste($previa);
            $this->soltarBaseSiExiste($temporal);

            throw $e;
        }

        $this->soltarBaseSiExiste($temporal);

        return "Base activa reemplazada; la anterior quedó íntegra en la base {$previa} (bórrala cuando confirmes que todo funciona).";
    }

    private function restaurarEnBaseNueva(string $volcado, string $destino): string
    {
        if ($this->existeBase($destino) && $this->tablas($destino) !== []) {
            throw new RuntimeException("La base de destino {$destino} ya tiene tablas; no se sobrescribe. Elige otra con --base-destino.");
        }

        $laCreamos = ! $this->existeBase($destino);
        $this->crearBase($destino);

        try {
            $this->importar($volcado, $destino);

            if ($this->tablas($destino) === []) {
                throw new RuntimeException('El volcado no creó ninguna tabla.');
            }
        } catch (\Throwable $e) {
            if ($laCreamos) {
                $this->soltarBaseSiExiste($destino);
            }

            throw $e;
        }

        return "Base restaurada en {$destino} (la base activa no se tocó).";
    }

    /**
     * El volcado se importa a una base elegida por nosotros; si trae USE/CREATE/DROP DATABASE podría escribir en otra.
     */
    private function rechazarSentenciasPeligrosas(string $volcado): void
    {
        $flujo = fopen($volcado, 'rb');

        if ($flujo === false) {
            throw new RuntimeException('No se pudo leer el volcado.');
        }

        try {
            while (($linea = fgets($flujo)) !== false) {
                if (preg_match('/^\s*(USE\s|CREATE\s+DATABASE|DROP\s+DATABASE)/i', $linea) === 1) {
                    throw new RuntimeException('El volcado contiene sentencias USE/CREATE/DROP DATABASE; por seguridad no se importa.');
                }
            }
        } finally {
            fclose($flujo);
        }
    }

    /**
     * RENAME TABLE no mueve vistas con seguridad, ni rutinas, ni tablas con disparadores entre bases.
     */
    private function exigirSoloTablas(string $base): void
    {
        $otros = (int) trim($this->consultar(
            "SELECT (SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = '{$base}')"
            ." + (SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = '{$base}')"
            ." + (SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '{$base}')"
        ));

        if ($otros > 0) {
            throw new RuntimeException("La base {$base} tiene vistas, disparadores o rutinas: no se puede reemplazar en un solo paso. Restaura en otra base con --base-destino y cambia DB_DATABASE en el .env.");
        }
    }

    /**
     * @return list<string>
     */
    private function tablas(string $base): array
    {
        $salida = trim($this->consultar(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$base}' AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"
        ));

        return $salida === '' ? [] : array_values(array_filter(array_map('trim', explode("\n", $salida))));
    }

    private function existeBase(string $base): bool
    {
        return (int) trim($this->consultar("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '{$base}'")) > 0;
    }

    private function crearBase(string $base): void
    {
        $this->ejecutar("CREATE DATABASE IF NOT EXISTS `{$base}` CHARACTER SET utf8mb4");
    }

    private function soltarBaseSiExiste(string $base): void
    {
        try {
            $this->ejecutar("DROP DATABASE IF EXISTS `{$base}`");
        } catch (\Throwable) {
            // Es limpieza: el error original es el que importa.
        }
    }

    private function importar(string $volcado, string $base): void
    {
        $flujo = fopen($volcado, 'rb');

        if ($flujo === false) {
            throw new RuntimeException('No se pudo leer el volcado.');
        }

        try {
            $this->correr($this->argumentos($base), $flujo);
        } finally {
            fclose($flujo);
        }
    }

    private function ejecutar(string $sql): void
    {
        $this->correr([...$this->argumentos(), '-e', $sql]);
    }

    private function consultar(string $sql): string
    {
        return $this->correr([...$this->argumentos(), '-N', '-B', '-e', $sql]);
    }

    /**
     * @return list<string>
     */
    private function argumentos(?string $base = null): array
    {
        $config = (array) config('database.connections.'.config('database.default'));
        $socket = (string) ($config['unix_socket'] ?? '');

        return array_values(array_filter([
            (string) config('menuonl.respaldos.mysql_path'),
            '--default-character-set=utf8mb4',
            $socket !== '' ? '--socket='.$socket : '--host='.($config['host'] ?? '127.0.0.1'),
            $socket !== '' ? null : '--port='.($config['port'] ?? '3306'),
            '--user='.$this->credenciales()[0],
            $base !== null ? '--database='.$base : null,
        ], fn (?string $argumento): bool => $argumento !== null));
    }

    /**
     * Usuario/clave para restaurar (RESTORE_DB_*); si no se definen, los de la conexión de la aplicación.
     *
     * @return array{0: string, 1: string}
     */
    private function credenciales(): array
    {
        $config = (array) config('database.connections.'.config('database.default'));
        $usuario = (string) config('menuonl.respaldos.restauracion.usuario');

        return $usuario !== ''
            ? [$usuario, (string) config('menuonl.respaldos.restauracion.password')]
            : [(string) ($config['username'] ?? ''), (string) ($config['password'] ?? '')];
    }

    /**
     * @param  list<string>  $comando
     * @param  resource|null  $entrada
     */
    private function correr(array $comando, $entrada = null): string
    {
        $proceso = Process::env(['MYSQL_PWD' => $this->credenciales()[1]])
            ->timeout((int) config('menuonl.respaldos.timeout_segundos'));

        if ($entrada !== null) {
            $proceso = $proceso->input($entrada);
        }

        $resultado = $proceso->run($comando);

        if ($resultado->failed()) {
            $clave = $this->credenciales()[1];
            $mensaje = trim($resultado->errorOutput());
            $mensaje = $clave === '' ? $mensaje : str_replace($clave, '[oculto]', $mensaje);

            throw new RuntimeException('El cliente de MariaDB falló (código '.$resultado->exitCode().'): '.mb_substr($mensaje, 0, 300));
        }

        return $resultado->output();
    }

    /**
     * Prepares the images: copies them next to their final place (same disk) without touching what is in use.
     *
     * @return array{staging: string, destino: string, reemplaza: bool, archivos: int}
     *
     * @throws RuntimeException
     */
    public function prepararImagenes(string $origen, string $destino, bool $reemplazaLasVivas): array
    {
        if (! $reemplazaLasVivas && file_exists($destino) && (! is_dir($destino) || count(array_diff(scandir($destino) ?: [], ['.', '..'])) > 0)) {
            throw new RuntimeException('La carpeta de imágenes de destino ya existe y no está vacía; no se sobrescribe. Elige otra con --carpeta-imagenes.');
        }

        $padre = dirname($destino);

        if (! is_dir($padre) && ! mkdir($padre, 0750, true) && ! is_dir($padre)) {
            throw new RuntimeException('No se pudo crear la carpeta padre de las imágenes.');
        }

        $staging = $padre.DIRECTORY_SEPARATOR.'.imagenes-nuevas-'.bin2hex(random_bytes(4));
        $archivos = $this->copiarArbol($origen, $staging);

        return ['staging' => $staging, 'destino' => $destino, 'reemplaza' => $reemplazaLasVivas, 'archivos' => $archivos];
    }

    /**
     * Puts the prepared images in place with renames. The previous folder, if any, is kept with a dated name.
     *
     * @param  array{staging: string, destino: string, reemplaza: bool, archivos: int}  $preparadas
     *
     * @throws RuntimeException
     */
    public function publicarImagenes(array $preparadas): string
    {
        $destino = $preparadas['destino'];
        $previa = null;

        if (file_exists($destino)) {
            if (! $preparadas['reemplaza']) {
                $this->vaciarYQuitar($destino);
            } else {
                $previa = $destino.'.previa-'.CarbonImmutable::now('UTC')->format('YmdHis');

                if (! rename($destino, $previa)) {
                    $this->descartarImagenes($preparadas);

                    throw new RuntimeException('No se pudo apartar la carpeta de imágenes actual; no se reemplazó nada.');
                }
            }
        }

        if (! rename($preparadas['staging'], $destino)) {
            if ($previa !== null) {
                rename($previa, $destino);
            }

            $this->descartarImagenes($preparadas);

            throw new RuntimeException('No se pudo colocar la carpeta de imágenes restaurada; se dejó la anterior.');
        }

        return $previa !== null
            ? "Imágenes reemplazadas ({$preparadas['archivos']} archivos); las anteriores quedaron en ".basename($previa).'.'
            : "Imágenes restauradas en {$destino} ({$preparadas['archivos']} archivos).";
    }

    /**
     * @param  array{staging: string, destino: string, reemplaza: bool, archivos: int}  $preparadas
     */
    public function descartarImagenes(array $preparadas): void
    {
        if (is_dir($preparadas['staging'])) {
            $this->vaciarYQuitar($preparadas['staging']);
        }
    }

    private function copiarArbol(string $origen, string $destino): int
    {
        if (! mkdir($destino, 0750, true) && ! is_dir($destino)) {
            throw new RuntimeException('No se pudo crear la carpeta temporal de imágenes.');
        }

        $archivos = 0;
        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($origen, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var \SplFileInfo $elemento */
        foreach ($iterador as $elemento) {
            $relativo = substr($elemento->getPathname(), strlen($origen) + 1);
            $hacia = $destino.DIRECTORY_SEPARATOR.$relativo;

            if ($elemento->isLink()) {
                continue;
            }

            if ($elemento->isDir()) {
                if (! is_dir($hacia) && ! mkdir($hacia, 0750, true)) {
                    $this->vaciarYQuitar($destino);

                    throw new RuntimeException('No se pudo crear una subcarpeta de imágenes.');
                }

                continue;
            }

            if (! copy($elemento->getPathname(), $hacia)) {
                $this->vaciarYQuitar($destino);

                throw new RuntimeException('No se pudo copiar una imagen; no se tocó nada en uso.');
            }

            $archivos++;
        }

        return $archivos;
    }

    private function vaciarYQuitar(string $ruta): void
    {
        if (is_file($ruta)) {
            unlink($ruta);

            return;
        }

        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($ruta, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterador as $elemento) {
            $elemento->isDir() && ! $elemento->isLink() ? rmdir($elemento->getPathname()) : unlink($elemento->getPathname());
        }

        rmdir($ruta);
    }

    private function rutaNormalizada(string $ruta): string
    {
        $real = realpath($ruta);

        return strtolower(str_replace('\\', '/', $real !== false ? $real : $ruta));
    }
}
