<?php

namespace App\Console\Commands;

use App\Models\Restaurante;
use App\Services\ContactoSoporte;
use App\Services\MarcadoresPendientes;
use App\Services\RespaldoService;
use App\Services\SondaDeCola;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Revisa la configuración de producción (docs/despliegue.md) y dice con claridad qué está mal.
 *
 * Nunca imprime valores secretos: solo nombres de entorno, de mailer y de conexión, y si cada ajuste cumple o no.
 */
class VerificarProduccionMenuonl extends Command
{
    protected $signature = 'menuonl:verificar-produccion
        {--espera=15 : Segundos que se espera a que el worker procese el trabajo de prueba}
        {--sin-worker : Omite la comprobación del worker (no recomendado)}';

    protected $description = 'Verifica la configuración de producción y que el worker de colas esté procesando';

    /**
     * @var list<array{ok: bool, nombre: string, detalle: string}>
     */
    private array $resultados = [];

    public function handle(SondaDeCola $sonda, RespaldoService $respaldos): int
    {
        $this->revisar('APP_ENV', config('app.env') === 'production', 'es "production"', 'es "'.config('app.env').'"; debe ser "production"');
        $this->revisar('APP_DEBUG', config('app.debug') === false, 'está apagado', 'está encendido; debe ser false (muestra errores internos)');
        $this->revisar('APP_URL', str_starts_with(strtolower((string) config('app.url')), 'https://'), 'usa https', 'no usa https; las cookies seguras y los QR necesitan https://');

        $mailer = (string) config('mail.default');
        $this->revisar('MAIL_MAILER', ! in_array($mailer, ['log', 'array'], true), 'es "'.$mailer.'"', 'es "'.$mailer.'"; las invitaciones no llegarían a nadie');

        $cola = (string) config('queue.default');
        $this->revisar('QUEUE_CONNECTION', ! in_array($cola, ['sync', 'null'], true), 'es "'.$cola.'"', 'es "'.$cola.'"; los trabajos no se procesarían en segundo plano');

        $this->revisar('SOPORTE_WHATSAPP', app(ContactoSoporte::class)->numero() !== null, 'está definida', 'falta o no es válida (10 a 15 dígitos, con lada); la portada no mostraría sus botones de WhatsApp y los propietarios de "Solo menú" no verían el botón de soporte');
        $this->revisar('APP_LOCALE', config('app.locale') === 'es', 'es "es"', 'es "'.config('app.locale').'"; debe ser "es"');

        $this->revisarWorker($sonda, in_array($cola, ['sync', 'null'], true));
        $this->avisos();
        $this->avisarDeMarcadoresPendientes();
        $this->avisarDeReservasSinAvisoDePrivacidad();
        $this->avisarDelUltimoRespaldo($respaldos);

        $fallas = array_filter($this->resultados, fn (array $r): bool => ! $r['ok']);

        $this->newLine();

        if ($fallas === []) {
            $this->info('Todo en orden: la configuración de producción es correcta.');

            return self::SUCCESS;
        }

        $this->error(count($fallas).' problema(s) grave(s). Corrígelos antes de recibir clientes (ver docs/despliegue.md).');

        return self::FAILURE;
    }

    private function revisar(string $nombre, bool $ok, string $detalleOk, string $detalleFalla): void
    {
        $this->resultados[] = ['ok' => $ok, 'nombre' => $nombre, 'detalle' => $ok ? $detalleOk : $detalleFalla];

        $ok
            ? $this->line("  <info>[OK]</info>     {$nombre} {$detalleOk}")
            : $this->line("  <error>[FALLA]</error>  {$nombre} {$detalleFalla}");
    }

    private function revisarWorker(SondaDeCola $sonda, bool $colaSincrona): void
    {
        if ($this->option('sin-worker')) {
            $this->line('  <comment>[OMITIDO]</comment> Worker de colas: comprobación omitida con --sin-worker');

            return;
        }

        if ($colaSincrona) {
            $this->line('  <comment>[OMITIDO]</comment> Worker de colas: no se puede comprobar con una cola síncrona');

            return;
        }

        if (! $sonda->cacheCompartida()) {
            $this->revisar('Worker de colas', false, '', 'no se puede comprobar con CACHE_STORE="'.config('cache.default').'"; usa database, file o redis');

            return;
        }

        try {
            $vivo = $sonda->sondear(max(0, (int) $this->option('espera')));
        } catch (Throwable $e) {
            $this->revisar('Worker de colas', false, '', 'no se pudo encolar el trabajo de prueba ('.class_basename($e).'); revisa la base de datos o Redis');

            return;
        }

        $this->revisar('Worker de colas', $vivo, 'procesó el trabajo de prueba', 'no procesó el trabajo de prueba en '.max(0, (int) $this->option('espera')).' s; revisa Supervisor o systemd (deploy/README.md)');
    }

    /**
     * Warns when the newest backup in the disk is older than the allowed age (or there is none). Never changes the exit code.
     */
    private function avisarDelUltimoRespaldo(RespaldoService $respaldos): void
    {
        $maximo = max(1, (int) config('menuonl.respaldos.horas_maximas_sin_respaldo'));

        try {
            $fecha = $respaldos->fechaDelUltimoRespaldo();
        } catch (Throwable $e) {
            $this->line('  <comment>[AVISO]</comment>  No se pudo consultar el disco de respaldos ('.class_basename($e).'); revisa BACKUP_* en el .env.');

            return;
        }

        if ($fecha === null) {
            $this->line('  <comment>[AVISO]</comment>  No hay ningún respaldo en el disco de respaldos: php artisan menuonl:backup');

            return;
        }

        $horas = (int) floor(abs($fecha->diffInHours(CarbonImmutable::now('UTC'))));

        $horas > $maximo
            ? $this->line("  <comment>[AVISO]</comment>  El último respaldo exitoso tiene {$horas} horas (máximo {$maximo}). Revisa storage/logs/laravel.log y el cron de schedule:run.")
            : $this->line("  <info>[OK]</info>     Último respaldo hace {$horas} horas");
    }

    /**
     * Warns when the home page or the legal pages still show placeholder text ([PRECIO], [CONFIRMAR]...). Never changes the exit code.
     */
    private function avisarDeMarcadoresPendientes(): void
    {
        try {
            $pendientes = app(MarcadoresPendientes::class)->enPaginasPublicas();
        } catch (Throwable $e) {
            $this->line('  <comment>[AVISO]</comment>  No se pudo revisar la portada ('.class_basename($e).').');

            return;
        }

        if ($pendientes === []) {
            $this->line('  <info>[OK]</info>     La portada y las páginas legales no tienen marcadores pendientes');

            return;
        }

        foreach ($pendientes as $ruta => $marcadores) {
            $this->line("  <comment>[AVISO]</comment>  {$ruta} aún muestra texto pendiente: ".implode(' ', $marcadores).'. No publiques hasta escribirlo.');
        }
    }

    /**
     * Warns when some active restaurant takes reservations but the privacy notice is still unwritten (its form would not
     * link to it). Never changes the exit code.
     */
    private function avisarDeReservasSinAvisoDePrivacidad(): void
    {
        try {
            $conReservas = Restaurante::query()
                ->where('estado', 'activo')
                ->where('reservas_activas', true)
                ->lazy()
                ->contains(fn (Restaurante $restaurante): bool => $restaurante->reservasDisponibles());

            if (! $conReservas) {
                return;
            }

            if (app(MarcadoresPendientes::class)->privacidadPendiente()) {
                $this->line('  <comment>[AVISO]</comment>  Hay restaurantes con reservas activas y el aviso de privacidad (/privacidad) sigue sin escribirse: sus formularios no lo enlazan. No publiques hasta escribirlo.');

                return;
            }

            $this->line('  <info>[OK]</info>     El aviso de privacidad está escrito y los formularios de reservas lo enlazan');
        } catch (Throwable $e) {
            $this->line('  <comment>[AVISO]</comment>  No se pudo revisar el aviso de privacidad de las reservas ('.class_basename($e).').');
        }
    }

    /**
     * Informational notes about the queue; they never change the exit code.
     */
    private function avisos(): void
    {
        try {
            if (config('queue.default') === 'database' && Schema::hasTable('failed_jobs')) {
                $falladas = DB::table('failed_jobs')->count();

                if ($falladas > 0) {
                    $this->line("  <comment>[AVISO]</comment>  Hay {$falladas} trabajo(s) fallido(s): php artisan queue:failed");
                }
            }
        } catch (Throwable) {
            // Solo informativo: si la base no responde, el resto de la verificación ya lo habrá dicho.
        }
    }
}
