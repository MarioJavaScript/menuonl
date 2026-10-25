<?php

namespace App\Console\Commands;

use App\Services\ContactoSoporte;
use App\Services\SondaDeCola;
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

    public function handle(SondaDeCola $sonda): int
    {
        $this->revisar('APP_ENV', config('app.env') === 'production', 'es "production"', 'es "'.config('app.env').'"; debe ser "production"');
        $this->revisar('APP_DEBUG', config('app.debug') === false, 'está apagado', 'está encendido; debe ser false (muestra errores internos)');
        $this->revisar('APP_URL', str_starts_with(strtolower((string) config('app.url')), 'https://'), 'usa https', 'no usa https; las cookies seguras y los QR necesitan https://');

        $mailer = (string) config('mail.default');
        $this->revisar('MAIL_MAILER', ! in_array($mailer, ['log', 'array'], true), 'es "'.$mailer.'"', 'es "'.$mailer.'"; las invitaciones no llegarían a nadie');

        $cola = (string) config('queue.default');
        $this->revisar('QUEUE_CONNECTION', ! in_array($cola, ['sync', 'null'], true), 'es "'.$cola.'"', 'es "'.$cola.'"; los trabajos no se procesarían en segundo plano');

        $this->revisar('SOPORTE_WHATSAPP', app(ContactoSoporte::class)->enlaceWhatsapp() !== null, 'está definida', 'falta o no tiene dígitos; los propietarios de "Solo menú" no verían el botón de soporte');
        $this->revisar('APP_LOCALE', config('app.locale') === 'es', 'es "es"', 'es "'.config('app.locale').'"; debe ser "es"');

        $this->revisarWorker($sonda, in_array($cola, ['sync', 'null'], true));
        $this->avisos();

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
