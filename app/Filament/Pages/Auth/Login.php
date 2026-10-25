<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Panel login with our own attempt limit, on top of Filament's per-IP throttle.
 *
 * Failed attempts are counted per email + IP and per IP alone. Once a limit is reached the
 * login is refused for every credential (a correct password included) until the lock expires,
 * and the message is the same whether or not the account exists.
 */
class Login extends BaseLogin
{
    /**
     * Registered through ->login(), so panel page discovery must skip it.
     */
    protected static bool $isDiscovered = false;

    /**
     * Quien solo pertenece a restaurantes sin panel por su plan sí inicia sesión: así llega a la pantalla que le
     * explica su plan y le ofrece contacto, en lugar de un error de credenciales que no es verdad.
     */
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        return parent::isUserAllowedToAccessPanel($user)
            || ($user instanceof User && $user->debeVerElAvisoDePlanSinPanel());
    }

    public function authenticate(): ?LoginResponse
    {
        $claves = $this->clavesDeIntentos();

        if ($this->estaBloqueado($claves)) {
            throw ValidationException::withMessages([
                'data.email' => 'Demasiados intentos fallidos. Espera unos minutos antes de volver a intentarlo.',
            ]);
        }

        try {
            $respuesta = parent::authenticate();
        } catch (ValidationException $e) {
            $bloqueo = (int) config('menuonl.login.bloqueo_segundos');

            foreach ($claves as $clave) {
                RateLimiter::hit($clave, $bloqueo);
            }

            throw $e;
        }

        if ($respuesta !== null) {
            RateLimiter::clear($claves['cuenta']);
        }

        return $respuesta;
    }

    /**
     * @return array{cuenta: string, ip: string}
     */
    protected function clavesDeIntentos(): array
    {
        $ip = (string) request()->ip();
        $email = mb_strtolower(trim((string) ($this->data['email'] ?? '')));

        return [
            'cuenta' => 'login:cuenta:'.sha1($email.'|'.$ip),
            'ip' => 'login:ip:'.sha1($ip),
        ];
    }

    /**
     * @param  array{cuenta: string, ip: string}  $claves
     */
    protected function estaBloqueado(array $claves): bool
    {
        return RateLimiter::tooManyAttempts($claves['cuenta'], (int) config('menuonl.login.max_intentos_cuenta'))
            || RateLimiter::tooManyAttempts($claves['ip'], (int) config('menuonl.login.max_intentos_ip'));
    }
}
