<?php

namespace App\Console\Commands;

use App\Mail\RespaldoVencidoMail;
use App\Services\RespaldoService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AlertarRespaldoMenuonl extends Command
{
    protected $signature = 'menuonl:backup-alerta';

    /**
     * @var array<int, string>
     */
    protected $aliases = ['menuonl:alertar-respaldo'];

    protected $description = 'Avisa por correo (una vez al día) si el último respaldo falta o es más viejo de lo permitido. Sale con error mientras el problema siga';

    public function handle(RespaldoService $respaldos): int
    {
        $diagnostico = $respaldos->diagnosticarAntiguedad();

        if ($diagnostico['problema'] === null) {
            $this->info("Respaldo al día: el último tiene {$diagnostico['horas']} hora(s) (máximo {$diagnostico['maximo']}).");

            return self::SUCCESS;
        }

        Log::error('menuonl:backup-alerta - El respaldo falta o está vencido.', $diagnostico);
        $this->error('El respaldo falta o está vencido ('.$diagnostico['problema'].').');

        $this->avisar($diagnostico);

        return self::FAILURE;
    }

    /**
     * @param  array{problema: string|null, horas: int|null, maximo: int}  $diagnostico
     */
    private function avisar(array $diagnostico): void
    {
        $destino = trim((string) config('menuonl.respaldos.alerta_correo'));

        if ($destino === '' || filter_var($destino, FILTER_VALIDATE_EMAIL) === false) {
            $this->warn('Sin aviso por correo: BACKUP_ALERT_EMAIL está vacío o no es un correo válido.');

            return;
        }

        if (in_array((string) config('mail.default'), ['log', 'array'], true)) {
            $this->warn('Sin aviso por correo: MAIL_MAILER no envía correos reales.');

            return;
        }

        // Un solo aviso por día (en la zona del respaldo), aunque el comando corra más veces.
        $clave = 'menuonl:respaldo:alerta:'.CarbonImmutable::now(RespaldoService::zonaHorariaProgramada())->toDateString();

        if (! Cache::add($clave, true, now()->addHours(36))) {
            $this->line('Ya se avisó hoy; no se repite.');

            return;
        }

        try {
            Mail::to($destino)->send(new RespaldoVencidoMail($diagnostico['problema'], $diagnostico['horas'], $diagnostico['maximo']));
        } catch (Throwable $e) {
            Cache::forget($clave);
            Log::error('menuonl:backup-alerta - No se pudo enviar el aviso por correo.', ['excepcion' => $e::class]);
            $this->warn('No se pudo enviar el aviso por correo; se reintentará en la próxima revisión.');

            return;
        }

        $this->info('Aviso enviado por correo.');
    }
}
