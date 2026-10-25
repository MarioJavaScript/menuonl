<?php

namespace App\Console\Commands;

use App\Models\CodigoQr;
use App\Services\RegistroDeEscaneos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Herramienta manual (no es una prueba automática): lanza varios procesos PHP que cuentan escaneos del mismo código a la
 * vez y comprueba que el total es exacto. Por defecto usa un archivo SQLite temporal propio; nunca toca la base de la
 * aplicación. Para MariaDB hay que indicar una conexión cuyo nombre de base termine en `_test` o `_prueba`.
 * Ver docs/integracion-dashboard.md, sección 13.
 */
class ProbarConcurrenciaDeEscaneos extends Command
{
    protected $signature = 'menuonl:probar-concurrencia-escaneos
        {--procesos=8 : Procesos PHP simultáneos}
        {--escaneos=50 : Escaneos que cuenta cada proceso}
        {--conexion= : Conexión de una base de pruebas (nombre terminado en _test o _prueba); sin esto, SQLite temporal}
        {--hijo : Uso interno}
        {--base= : Uso interno}
        {--codigo-id= : Uso interno}';

    protected $description = 'Herramienta manual: cuenta escaneos desde varios procesos PHP a la vez y verifica que el total es exacto';

    private const string CONEXION = 'concurrencia';

    public function handle(RegistroDeEscaneos $escaneos): int
    {
        if (app()->isProduction()) {
            $this->error('Esta herramienta no se ejecuta en producción.');

            return self::FAILURE;
        }

        try {
            $ruta = $this->prepararConexion();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return $this->option('hijo') ? $this->comoHijo($escaneos) : $this->comoPadre($ruta);
    }

    /**
     * Deja la conexión de trabajo como predeterminada y devuelve la ruta del SQLite temporal (o null con --conexion).
     */
    private function prepararConexion(): ?string
    {
        $conexion = (string) $this->option('conexion');

        if ($conexion !== '') {
            $base = (string) config("database.connections.{$conexion}.database");

            if (config("database.connections.{$conexion}") === null || preg_match('/_(test|prueba)$/', $base) !== 1) {
                throw new RuntimeException('La conexión indicada debe existir y su base de datos terminar en _test o _prueba.');
            }

            config(['database.connections.'.self::CONEXION => config("database.connections.{$conexion}")]);
            DB::setDefaultConnection(self::CONEXION);

            return null;
        }

        $ruta = (string) $this->option('base') ?: sys_get_temp_dir().DIRECTORY_SEPARATOR.'menuonl-concurrencia-'.bin2hex(random_bytes(4)).'.sqlite';

        config(['database.connections.'.self::CONEXION => [
            'driver' => 'sqlite',
            'database' => $ruta,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 10000,
            'journal_mode' => 'wal',
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ]]);
        DB::setDefaultConnection(self::CONEXION);

        return $ruta;
    }

    private function comoPadre(?string $ruta): int
    {
        $procesos = max(1, (int) $this->option('procesos'));
        $escaneos = max(1, (int) $this->option('escaneos'));

        if ($ruta !== null) {
            touch($ruta);
        }

        Artisan::call('migrate', ['--database' => self::CONEXION, '--force' => true]);
        $codigo = CodigoQr::factory()->deMesa('1')->create();
        $esperado = $procesos * $escaneos;

        $this->info("Contando {$esperado} escaneos del código {$codigo->codigo} desde {$procesos} procesos...");
        $inicio = microtime(true);

        $hijos = [];
        for ($i = 0; $i < $procesos; $i++) {
            $comando = [PHP_BINARY, base_path('artisan'), 'menuonl:probar-concurrencia-escaneos', '--hijo', "--escaneos={$escaneos}", "--codigo-id={$codigo->getKey()}"];
            $comando[] = $ruta !== null ? "--base={$ruta}" : '--conexion='.$this->option('conexion');
            $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, base_path());
            $hijos[] = [$proceso, $tuberias];
        }

        $fallos = 0;
        foreach ($hijos as [$proceso, $tuberias]) {
            $salida = (string) stream_get_contents($tuberias[1]);
            stream_get_contents($tuberias[2]);
            proc_close($proceso);
            $fallos += preg_match('/fallos=(\d+)/', $salida, $coincidencia) === 1 ? (int) $coincidencia[1] : $escaneos;
        }

        $obtenido = (int) DB::table('escaneos_diarios')->where('codigo_qr_id', $codigo->getKey())->sum('conteo');
        $segundos = round(microtime(true) - $inicio, 2);

        if ($ruta !== null) {
            DB::disconnect(self::CONEXION);
            foreach ([$ruta, $ruta.'-wal', $ruta.'-shm'] as $archivo) {
                @unlink($archivo);
            }
        }

        $this->line("Esperado: {$esperado} · Obtenido: {$obtenido} · Escrituras fallidas: {$fallos} · {$segundos} s");

        if ($obtenido === $esperado && $fallos === 0) {
            $this->info('Correcto: no se perdió ningún incremento.');

            return self::SUCCESS;
        }

        $this->error('Hay diferencia: revisa el motor, el tiempo de espera de bloqueos y los registros.');

        return self::FAILURE;
    }

    private function comoHijo(RegistroDeEscaneos $escaneos): int
    {
        $codigo = CodigoQr::withoutGlobalScopes()->with(['restaurante' => fn ($consulta) => $consulta->withTrashed()])->findOrFail((int) $this->option('codigo-id'));
        $fila = [
            'restaurante_id' => (int) $codigo->restaurante_id,
            'codigo_qr_id' => (int) $codigo->getKey(),
            'fecha' => $escaneos->diaLocal($codigo->restaurante?->zona_horaria),
            'tipo' => $codigo->tipo->value,
            'conteo' => 1,
        ];

        $fallos = 0;
        for ($i = 0, $total = (int) $this->option('escaneos'); $i < $total; $i++) {
            $fallos += $escaneos->registrar($fila) ? 0 : 1;
        }

        $this->line("fallos={$fallos}");

        return self::SUCCESS;
    }
}
