<?php

namespace App\Jobs;

use App\Mail\InvitacionMail;
use App\Models\Invitacion;
use App\Services\InvitacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends the invitation email from the queue.
 *
 * The job carries the plain token, so it is encrypted (ShouldBeEncrypted) in both the jobs and failed_jobs tables.
 * It never sends twice: it does nothing if the invitation was revoked, accepted, expired, replaced by a newer token
 * or already sent, so a retry or a manual resend cannot duplicate or resurrect an invitation.
 */
class EnviarInvitacionJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 4;

    public int $timeout = 60;

    public function __construct(
        public int $invitacionId,
        #[\SensitiveParameter] public string $token,
    ) {}

    /**
     * Seconds to wait before each retry.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(): void
    {
        $invitacion = $this->invitacionVigente();

        if ($invitacion === null || $invitacion->enviada_en !== null) {
            return;
        }

        Mail::to($invitacion->email)->send(new InvitacionMail($invitacion, $invitacion->restaurante, $this->token));

        Invitacion::withoutGlobalScopes()->whereKey($invitacion->getKey())->update([
            'enviada_en' => now(),
            'envio_fallido_en' => null,
            'envio_error' => null,
        ]);
    }

    /**
     * Called once the last attempt failed: the invitation stays created and is flagged so it can be resent.
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('EnviarInvitacionJob: no se pudo enviar el correo de la invitación.', [
            'invitacion_id' => $this->invitacionId,
            'excepcion' => $exception::class,
        ]);

        if ($this->invitacionVigente() === null) {
            return;
        }

        Invitacion::withoutGlobalScopes()->whereKey($this->invitacionId)->update([
            'envio_fallido_en' => now(),
            'envio_error' => self::resumirError($exception, $this->token),
        ]);
    }

    /**
     * Short, secret-free description of the failure for the superadmin.
     */
    public static function resumirError(Throwable $exception, string $token = ''): string
    {
        $mensaje = $token === '' ? $exception->getMessage() : str_replace($token, '[token]', $exception->getMessage());

        return Str::limit(class_basename($exception).': '.trim(preg_replace('/\s+/', ' ', $mensaje) ?? ''), 250, '...');
    }

    /**
     * The invitation this token still belongs to, or null if it was revoked, accepted, expired or replaced.
     */
    private function invitacionVigente(): ?Invitacion
    {
        $invitacion = Invitacion::withoutGlobalScopes()->with('restaurante')->find($this->invitacionId);

        if (
            $invitacion === null
            || $invitacion->aceptada_en !== null
            || $invitacion->expira_en->isPast()
            || $invitacion->restaurante === null
            || ! hash_equals($invitacion->token, InvitacionService::hashear($this->token))
        ) {
            return null;
        }

        return $invitacion;
    }
}
