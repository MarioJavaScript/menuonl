<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use ZipArchive;

/**
 * Respaldo cifrado de la base y de las imágenes de los restaurantes (RNF-06).
 *
 * El .zip lleva el volcado, storage/app/public/restaurantes y un manifest.json. Nunca el .env.
 * El contenido va cifrado con AES-256; los nombres de archivo dentro del zip no se cifran.
 */
class RespaldoService
{
    private const PATRON_NOMBRE = '/^%s-(\d{8}-\d{6})\.zip$/';

    private const CARPETA_IMAGENES = 'restaurantes';

    /**
     * Crea el respaldo, lo sube al disco configurado y lo comprueba.
     *
     * @return array{nombre: string, bytes: int, archivos: int}
     *
     * @throws RuntimeException
     */
    public function crear(): array
    {
        $password = $this->password();
        $directorio = $this->crearDirectorioTemporal();

        try {
            $volcado = $this->volcarBase($directorio);
            $zipLocal = $directorio.DIRECTORY_SEPARATOR.'respaldo.zip';
            $archivos = $this->comprimir($zipLocal, $volcado, $password);

            $nombre = $this->prefijo().'-'.CarbonImmutable::now('UTC')->format('Ymd-His').'.zip';
            $bytes = (int) filesize($zipLocal);
            $this->subir($zipLocal, $nombre, $bytes);

            return ['nombre' => $nombre, 'bytes' => $bytes, 'archivos' => $archivos];
        } finally {
            $this->borrarDirectorio($directorio);
        }
    }

    /**
     * Borra los respaldos con más de `retencion_dias` días, según la fecha del nombre.
     * Solo toca archivos con el patrón de este comando.
     *
     * @return array<int, string> Nombres borrados.
     *
     * @throws RuntimeException
     */
    public function aplicarRetencion(): array
    {
        $limite = CarbonImmutable::now('UTC')->subDays(max(1, (int) config('menuonl.respaldos.retencion_dias')));
        $borrados = [];

        foreach ($this->listar() as $nombre) {
            $fecha = $this->fechaDelNombre($nombre);

            if ($fecha === null || ! $fecha->lt($limite)) {
                continue;
            }

            if (! $this->disco()->delete($nombre)) {
                throw new RuntimeException("No se pudo borrar el respaldo antiguo {$nombre}.");
            }

            $borrados[] = $nombre;
        }

        return $borrados;
    }

    /**
     * Nombres de respaldos del disco, del más reciente al más antiguo.
     *
     * @return array<int, string>
     */
    public function listar(): array
    {
        $patron = sprintf(self::PATRON_NOMBRE, preg_quote($this->prefijo(), '/'));

        $nombres = array_values(array_filter(
            array_map('basename', $this->disco()->files()),
            fn (string $nombre): bool => preg_match($patron, $nombre) === 1,
        ));

        rsort($nombres);

        return $nombres;
    }

    /**
     * Descarga un respaldo, lo descifra en un directorio temporal y comprueba que se pueda restaurar.
     * No toca la base ni los archivos en uso.
     *
     * @return array{nombre: string, archivos: int, tablas: int|null}
     *
     * @throws RuntimeException
     */
    public function verificar(?string $nombre = null): array
    {
        $nombre = $this->resolverNombre($nombre);
        $directorio = $this->crearDirectorioTemporal();

        try {
            return $this->comprobarContenido($this->descargarYDescifrar($nombre, $directorio), $nombre);
        } finally {
            $this->borrarDirectorio($directorio);
        }
    }

    /**
     * Descarga, descifra y comprueba un respaldo (incluida la suma SHA-256 del volcado) SIN tocar nada en uso.
     *
     * Es el primer paso de `menuonl:restaurar-respaldo`: si algo falla aquí, no se ha modificado ninguna base ni carpeta.
     * Quien la llame debe pasar `raiz` a limpiar() al terminar.
     *
     * @return array{nombre: string, raiz: string, base: string, motor: string, imagenes: string|null, archivos: int, tablas: int|null}
     *
     * @throws RuntimeException
     */
    public function prepararRestauracion(?string $nombre = null): array
    {
        $nombre = $this->resolverNombre($nombre);
        $raiz = $this->crearDirectorioTemporal();

        try {
            $restaurado = $this->descargarYDescifrar($nombre, $raiz);
            $resumen = $this->comprobarContenido($restaurado, $nombre);
        } catch (\Throwable $e) {
            $this->borrarDirectorio($raiz);

            throw $e;
        }

        $manifest = (array) json_decode((string) file_get_contents($restaurado.DIRECTORY_SEPARATOR.'manifest.json'), true);
        $archivoBase = (string) $manifest['base_de_datos'];
        $carpetaImagenes = $restaurado.DIRECTORY_SEPARATOR.self::CARPETA_IMAGENES;

        return [
            'nombre' => $nombre,
            'raiz' => $raiz,
            'base' => $restaurado.DIRECTORY_SEPARATOR.$archivoBase,
            'motor' => str_ends_with($archivoBase, '.sqlite') ? 'sqlite' : 'sql',
            'imagenes' => is_dir($carpetaImagenes) ? $carpetaImagenes : null,
            'archivos' => $resumen['archivos'],
            'tablas' => $resumen['tablas'],
        ];
    }

    /**
     * Borra el directorio temporal creado por prepararRestauracion().
     */
    public function limpiar(string $raiz): void
    {
        if (str_starts_with(basename($raiz), 'menuonl-respaldo-')) {
            $this->borrarDirectorio($raiz);
        }
    }

    /**
     * Fecha (UTC) del respaldo más reciente que hay en el disco, o null si no hay ninguno. Sale del nombre del archivo.
     *
     * @throws RuntimeException cuando no se puede leer el disco de respaldos
     */
    public function fechaDelUltimoRespaldo(): ?CarbonImmutable
    {
        foreach ($this->listar() as $nombre) {
            if (($fecha = $this->fechaDelNombre($nombre)) !== null) {
                return $fecha;
            }
        }

        return null;
    }

    /**
     * Hora (HH:MM) del respaldo diario. Un valor mal escrito en el .env cae a la hora segura de poco tráfico.
     */
    public static function horaProgramada(): string
    {
        $hora = (string) config('menuonl.respaldos.hora');

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) === 1 ? $hora : '04:00';
    }

    /**
     * Hora (HH:MM) en que se revisa que el respaldo diario exista. Un valor mal escrito cae a las 06:00.
     */
    public static function horaDeAlerta(): string
    {
        $hora = (string) config('menuonl.respaldos.alerta_hora');

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) === 1 ? $hora : '06:00';
    }

    /**
     * Si el respaldo más reciente es demasiado viejo, no existe o el disco no se pudo leer. Nunca incluye rutas ni mensajes de error.
     *
     * @return array{problema: string|null, horas: int|null, maximo: int} problema: null (todo bien), 'sin_respaldos', 'vencido' o 'disco_ilegible'
     */
    public function diagnosticarAntiguedad(): array
    {
        $maximo = max(1, (int) config('menuonl.respaldos.horas_maximas_sin_respaldo'));

        try {
            $fecha = $this->fechaDelUltimoRespaldo();
        } catch (\Throwable $e) {
            Log::warning('RespaldoService - No se pudo consultar el disco de respaldos.', ['excepcion' => $e::class]);

            return ['problema' => 'disco_ilegible', 'horas' => null, 'maximo' => $maximo];
        }

        if ($fecha === null) {
            return ['problema' => 'sin_respaldos', 'horas' => null, 'maximo' => $maximo];
        }

        $horas = (int) floor(abs($fecha->diffInHours(CarbonImmutable::now('UTC'))));

        return ['problema' => $horas > $maximo ? 'vencido' : null, 'horas' => $horas, 'maximo' => $maximo];
    }

    /**
     * Zona horaria en la que se interpreta la hora del respaldo.
     */
    public static function zonaHorariaProgramada(): string
    {
        $zona = (string) config('menuonl.respaldos.zona_horaria');

        return in_array($zona, \DateTimeZone::listIdentifiers(), true) ? $zona : 'America/Mexico_City';
    }

    private function resolverNombre(?string $nombre): string
    {
        if ($nombre !== null) {
            $patron = sprintf(self::PATRON_NOMBRE, preg_quote($this->prefijo(), '/'));
            if (basename($nombre) !== $nombre || preg_match($patron, $nombre) !== 1) {
                throw new RuntimeException("Nombre de respaldo inválido o con formato no permitido: {$nombre}.");
            }

            return $nombre;
        }

        return $this->listar()[0] ?? throw new RuntimeException('No hay respaldos en el disco.');
    }

    /**
     * Descarga el zip a $directorio, valida sus entradas (Zip Slip, tamaño) y lo descifra. Devuelve la carpeta extraída.
     *
     * @throws RuntimeException
     */
    private function descargarYDescifrar(string $nombre, string $directorio): string
    {
        $password = $this->password();
        $zipLocal = $directorio.DIRECTORY_SEPARATOR.'descargado.zip';
        $flujo = $this->disco()->readStream($nombre);

        if (! is_resource($flujo)) {
            throw new RuntimeException("No se pudo descargar {$nombre}.");
        }

        $destino = fopen($zipLocal, 'wb');
        if (! is_resource($destino)) {
            fclose($flujo);
            throw new RuntimeException('No se pudo crear el archivo temporal de descarga.');
        }

        try {
            stream_copy_to_stream($flujo, $destino);
        } finally {
            fclose($flujo);
            fclose($destino);
        }

        $zip = new ZipArchive;

        if ($zip->open($zipLocal) !== true) {
            throw new RuntimeException("{$nombre} no es un zip válido.");
        }

        try {
            // Validación contra Zip Slip y Zip Bombs antes de extraer
            $totalDescomprimido = 0;
            $maxDescomprimido = 2 * 1024 * 1024 * 1024; // Límite de 2 GB de seguridad

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $nombreEntrada = (string) ($stat['name'] ?? '');
                $totalDescomprimido += (int) ($stat['size'] ?? 0);

                if ($totalDescomprimido > $maxDescomprimido) {
                    throw new RuntimeException("El archivo {$nombre} supera el tamaño seguro de descompresión.");
                }

                if (
                    str_contains($nombreEntrada, '..')
                    || str_starts_with($nombreEntrada, '/')
                    || str_starts_with($nombreEntrada, '\\')
                    || preg_match('/^[a-zA-Z]:/', $nombreEntrada)
                    || str_contains($nombreEntrada, "\0")
                ) {
                    throw new RuntimeException("Ruta no autorizada en el archivo de respaldo: {$nombreEntrada}");
                }

                if (
                    $nombreEntrada !== 'manifest.json'
                    && ! preg_match('/^database\.(sql|sqlite)$/', $nombreEntrada)
                    && ! str_starts_with($nombreEntrada, self::CARPETA_IMAGENES.'/')
                    && ! str_starts_with($nombreEntrada, 'imagenes/')
                    && $nombreEntrada !== self::CARPETA_IMAGENES
                    && $nombreEntrada !== 'imagenes'
                ) {
                    throw new RuntimeException("Entrada no reconocida en el respaldo: {$nombreEntrada}");
                }
            }

            $zip->setPassword($password);
            $restaurado = $directorio.DIRECTORY_SEPARATOR.'restaurado';

            if (! $zip->extractTo($restaurado)) {
                throw new RuntimeException('No se pudo descifrar el respaldo: contraseña incorrecta o archivo dañado.');
            }
        } finally {
            $zip->close();
        }

        return $restaurado;
    }

    /**
     * Vuelca la base de datos en $directorio y devuelve la ruta del volcado.
     */
    private function volcarBase(string $directorio): string
    {
        $conexion = (string) config('database.default');
        $config = (array) config("database.connections.{$conexion}");
        $driver = (string) ($config['driver'] ?? '');

        return match ($driver) {
            'sqlite' => $this->volcarSqlite($directorio, (string) ($config['database'] ?? '')),
            'mysql', 'mariadb' => $this->volcarMysql($directorio, $config),
            default => throw new RuntimeException("Driver de base de datos no soportado para respaldos: {$driver}."),
        };
    }

    private function volcarSqlite(string $directorio, string $origen): string
    {
        if ($origen === '' || $origen === ':memory:' || ! is_file($origen)) {
            throw new RuntimeException('La base SQLite no es un archivo respaldable.');
        }

        $destino = $directorio.DIRECTORY_SEPARATOR.'database.sqlite';

        // VACUUM INTO da una copia consistente aunque haya escrituras o WAL, a diferencia de copiar el archivo.
        $pdo = new PDO('sqlite:'.$origen);
        $pdo->exec("VACUUM INTO '".str_replace("'", "''", $destino)."'");
        $pdo = null;

        if (! is_file($destino) || filesize($destino) === 0) {
            throw new RuntimeException('La copia de SQLite quedó vacía.');
        }

        return $destino;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function volcarMysql(string $directorio, array $config): string
    {
        $destino = $directorio.DIRECTORY_SEPARATOR.'database.sql';
        $socket = (string) ($config['unix_socket'] ?? '');

        $database = (string) ($config['database'] ?? '');
        if ($database === '' || ! preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
            throw new RuntimeException("El nombre de la base de datos es inválido o no está definido: '{$database}'.");
        }

        // Usuario de solo lectura propio del respaldo (BACKUP_DB_USERNAME); si no se define, el de la aplicación.
        $usuarioRespaldo = (string) config('menuonl.respaldos.db_usuario');
        $usuario = $usuarioRespaldo !== '' ? $usuarioRespaldo : (string) ($config['username'] ?? '');
        $clave = $usuarioRespaldo !== '' ? (string) config('menuonl.respaldos.db_password') : (string) ($config['password'] ?? '');

        $comando = array_values(array_filter([
            (string) config('menuonl.respaldos.mysqldump_path'),
            '--single-transaction',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--result-file='.$destino,
            $socket !== '' ? '--socket='.$socket : '--host='.($config['host'] ?? '127.0.0.1'),
            $socket !== '' ? null : '--port='.($config['port'] ?? '3306'),
            '--user='.$usuario,
            '--',
            $database,
        ]));

        // La contraseña va por entorno para que no aparezca en la lista de procesos.
        $resultado = Process::env(['MYSQL_PWD' => $clave])
            ->timeout((int) config('menuonl.respaldos.timeout_segundos'))
            ->run($comando);

        if ($resultado->failed()) {
            throw new RuntimeException('mysqldump falló (código '.$resultado->exitCode().'): '.trim($resultado->errorOutput()));
        }

        if (! is_file($destino) || filesize($destino) === 0) {
            throw new RuntimeException('mysqldump no generó el volcado.');
        }

        return $destino;
    }

    /**
     * Arma el zip cifrado y devuelve cuántas imágenes incluyó.
     */
    private function comprimir(string $zipLocal, string $volcado, string $password): int
    {
        $zip = new ZipArchive;

        if ($zip->open($zipLocal, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo zip.');
        }

        $zip->setPassword($password);
        $entradas = [];
        $imagenes = 0;

        $zip->addFile($volcado, basename($volcado));
        $entradas[] = basename($volcado);

        $raiz = storage_path('app/public/'.self::CARPETA_IMAGENES);

        if (is_dir($raiz)) {
            $iterador = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS),
            );

            /** @var \SplFileInfo $archivo */
            foreach ($iterador as $archivo) {
                if (! $archivo->isFile() || $archivo->isLink() || str_starts_with($archivo->getFilename(), '.env')) {
                    continue;
                }

                $relativo = self::CARPETA_IMAGENES.'/'.str_replace('\\', '/', substr($archivo->getPathname(), strlen($raiz) + 1));
                $zip->addFile($archivo->getPathname(), $relativo);
                $entradas[] = $relativo;
                $imagenes++;
            }
        }

        $zip->addFromString('manifest.json', (string) json_encode([
            'creado_en' => CarbonImmutable::now('UTC')->toIso8601String(),
            'base_de_datos' => basename($volcado),
            'sha256_base' => hash_file('sha256', $volcado),
            'imagenes' => $imagenes,
        ], JSON_PRETTY_PRINT));
        $entradas[] = 'manifest.json';

        foreach ($entradas as $entrada) {
            if (! $zip->setEncryptionName($entrada, ZipArchive::EM_AES_256)) {
                throw new RuntimeException("No se pudo cifrar {$entrada}.");
            }
        }

        if (! $zip->close()) {
            throw new RuntimeException('No se pudo terminar el archivo zip.');
        }

        return $imagenes;
    }

    private function subir(string $zipLocal, string $nombre, int $bytes): void
    {
        $flujo = fopen($zipLocal, 'rb');

        if ($flujo === false) {
            throw new RuntimeException('No se pudo leer el zip local.');
        }

        try {
            $subido = $this->disco()->writeStream($nombre, $flujo);
        } finally {
            fclose($flujo);
        }

        if ($subido !== true || ! $this->disco()->exists($nombre) || $this->disco()->size($nombre) !== $bytes) {
            throw new RuntimeException("La subida de {$nombre} al disco de respaldos falló o quedó incompleta.");
        }
    }

    /**
     * @return array{nombre: string, archivos: int, tablas: int|null}
     */
    private function comprobarContenido(string $restaurado, string $nombre): array
    {
        $manifest = json_decode((string) @file_get_contents($restaurado.DIRECTORY_SEPARATOR.'manifest.json'), true);

        if (! is_array($manifest)) {
            throw new RuntimeException('El respaldo no trae un manifest.json válido.');
        }

        $nombreBase = (string) ($manifest['base_de_datos'] ?? '');
        if (basename($nombreBase) !== $nombreBase || preg_match('/^database\.(sql|sqlite)$/', $nombreBase) !== 1) {
            throw new RuntimeException('El manifest contiene un nombre de base de datos no permitido.');
        }

        $volcado = $restaurado.DIRECTORY_SEPARATOR.$nombreBase;
        $tablas = null;

        if (! is_file($volcado) || filesize($volcado) === 0) {
            throw new RuntimeException('El respaldo no trae el volcado de la base.');
        }

        // Los respaldos nuevos traen la suma SHA-256 del volcado; los anteriores a ese campo solo se validan por contenido.
        if (isset($manifest['sha256_base']) && ! hash_equals((string) $manifest['sha256_base'], (string) hash_file('sha256', $volcado))) {
            throw new RuntimeException('La suma de verificación del volcado no coincide: el respaldo está dañado.');
        }

        if (str_ends_with($volcado, '.sqlite')) {
            $pdo = new PDO('sqlite:'.$volcado);
            $integridad = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $tablas = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'")->fetchColumn();

            $pdo = null;

            if ($integridad !== 'ok' || $tablas === 0) {
                throw new RuntimeException('La base SQLite respaldada está dañada o vacía.');
            }
        } elseif (! str_contains((string) file_get_contents($volcado, false, null, 0, 5 * 1024 * 1024), 'CREATE TABLE')) {
            throw new RuntimeException('El volcado SQL no contiene tablas.');
        }

        $encontradas = 0;
        $carpeta = $restaurado.DIRECTORY_SEPARATOR.self::CARPETA_IMAGENES;

        if (is_dir($carpeta)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpeta, \FilesystemIterator::SKIP_DOTS)) as $archivo) {
                $encontradas += $archivo->isFile() ? 1 : 0;
            }
        }

        if ($encontradas !== (int) ($manifest['imagenes'] ?? -1)) {
            throw new RuntimeException("Las imágenes restauradas ({$encontradas}) no coinciden con el manifest.");
        }

        return ['nombre' => $nombre, 'archivos' => $encontradas, 'tablas' => $tablas];
    }

    private function fechaDelNombre(string $nombre): ?CarbonImmutable
    {
        $patron = sprintf(self::PATRON_NOMBRE, preg_quote($this->prefijo(), '/'));

        if (preg_match($patron, $nombre, $coincidencia) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Ymd-His', $coincidencia[1], 'UTC') ?: null;
    }

    private function password(): string
    {
        $password = (string) config('menuonl.respaldos.password');

        if ($password === '') {
            throw new RuntimeException('BACKUP_ARCHIVE_PASSWORD está vacío: no se generan respaldos sin cifrar.');
        }

        return $password;
    }

    private function prefijo(): string
    {
        return (string) config('menuonl.respaldos.prefijo', 'menuonl');
    }

    private function disco(): Filesystem
    {
        return Storage::disk((string) config('menuonl.respaldos.disco'));
    }

    private function crearDirectorioTemporal(): string
    {
        $directorio = sys_get_temp_dir().DIRECTORY_SEPARATOR.'menuonl-respaldo-'.bin2hex(random_bytes(8));

        if (! mkdir($directorio, 0700, true) && ! is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear el directorio temporal.');
        }

        return $directorio;
    }

    private function borrarDirectorio(string $directorio): void
    {
        if (! is_dir($directorio)) {
            return;
        }

        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directorio, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterador as $elemento) {
            $elemento->isDir() && ! $elemento->isLink() ? rmdir($elemento->getPathname()) : unlink($elemento->getPathname());
        }

        rmdir($directorio);
    }
}
