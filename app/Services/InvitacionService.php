<?php

namespace App\Services;

use App\Jobs\EnviarInvitacionJob;
use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Creates, finds and consumes user invitations.
 *
 * The plain token exists only in the email: the database keeps its SHA-256 hash, so a leaked
 * table cannot be used to accept invitations.
 */
class InvitacionService
{
    public const int DIAS_VIGENCIA = 7;

    public const int LONGITUD_TOKEN = 48;

    /**
     * Invite an email to a restaurant and send the link.
     *
     * Earlier pending invitations for the same email and restaurant are replaced. The invitation is always
     * created; the email goes out through the queue (EnviarInvitacionJob, encrypted because it carries the plain
     * token), so a mail failure never breaks the caller: it flags the invitation so it can be resent.
     *
     * @throws AuthorizationException when the inviter is neither superadmin nor propietario or gerente of the restaurant
     * @throws ValidationException when the role is not one of those offered (PermisosPanel::ROLES_OFRECIDOS) to that inviter
     */
    public function invitar(Restaurante $restaurante, string $email, string $rol, ?User $invitadoPor): Invitacion
    {
        if (
            $invitadoPor !== null
            && ! $invitadoPor->isSuperadmin()
            && ! in_array($invitadoPor->rolEn($restaurante), ['propietario', 'gerente'], true)
        ) {
            throw new AuthorizationException('Solo un propietario o gerente puede invitar usuarios.');
        }

        $ofrecidos = $invitadoPor === null
            ? PermisosPanel::ROLES_OFRECIDOS
            : PermisosPanel::rolesAsignables($invitadoPor, $restaurante->getKey());

        if (! in_array($rol, $ofrecidos, true)) {
            throw ValidationException::withMessages(['rol' => 'Ese rol no se puede asignar.']);
        }

        $token = Str::random(self::LONGITUD_TOKEN);
        $email = Str::lower(trim($email));

        $invitacion = DB::transaction(function () use ($restaurante, $email, $rol, $invitadoPor, $token): Invitacion {
            $this->consulta()
                ->where('restaurante_id', $restaurante->getKey())
                ->where('email', $email)
                ->whereNull('aceptada_en')
                ->delete();

            // The superadmin may invite for a restaurant other than the active tenant (new restaurant signup).
            return Invitacion::withoutEvents(fn (): Invitacion => Invitacion::create([
                'restaurante_id' => $restaurante->getKey(),
                'email' => $email,
                'rol' => $rol,
                'token' => self::hashear($token),
                'invitado_por' => $invitadoPor?->getKey(),
                'expira_en' => now()->addDays(self::DIAS_VIGENCIA),
            ]));
        });

        $this->enviarEnCola($invitacion, $token);

        return $invitacion;
    }

    /**
     * Resend a pending invitation with a fresh token and a fresh expiry date, on the same row.
     *
     * The previous link stops working, and any job still carrying the previous token does nothing, so the invitation
     * is neither duplicated nor sent twice with different links.
     *
     * @throws AuthorizationException when the user is neither superadmin nor propietario or gerente of the restaurant
     * @throws \LogicException when the invitation was already accepted
     */
    public function reenviar(Invitacion $invitacion, ?User $usuario): Invitacion
    {
        $restaurante = Restaurante::query()->withoutGlobalScopes()->findOrFail($invitacion->restaurante_id);

        if (
            $usuario !== null
            && ! $usuario->isSuperadmin()
            && ! in_array($usuario->rolEn($restaurante), ['propietario', 'gerente'], true)
        ) {
            throw new AuthorizationException('Solo un propietario o gerente puede reenviar invitaciones.');
        }

        $token = Str::random(self::LONGITUD_TOKEN);

        $renovada = $this->consulta()
            ->whereKey($invitacion->getKey())
            ->whereNull('aceptada_en')
            ->update([
                'token' => self::hashear($token),
                'expira_en' => now()->addDays(self::DIAS_VIGENCIA),
                'enviada_en' => null,
                'envio_fallido_en' => null,
                'envio_error' => null,
            ]);

        if ($renovada !== 1) {
            throw new \LogicException('La invitación ya fue aceptada o ya no existe.');
        }

        $invitacion = $this->consulta()->findOrFail($invitacion->getKey());
        $this->enviarEnCola($invitacion, $token);

        return $invitacion->refresh();
    }

    /**
     * Queue the email. If even queueing fails (queue down), the invitation stays created and is flagged as failed.
     */
    private function enviarEnCola(Invitacion $invitacion, string $token): void
    {
        try {
            EnviarInvitacionJob::dispatch($invitacion->getKey(), $token)->afterCommit();
        } catch (Throwable $e) {
            Log::warning('InvitacionService: no se pudo encolar el correo de la invitación.', [
                'invitacion_id' => $invitacion->getKey(),
                'excepcion' => $e::class,
            ]);

            $this->consulta()->whereKey($invitacion->getKey())->update([
                'envio_fallido_en' => now(),
                'envio_error' => EnviarInvitacionJob::resumirError($e, $token),
            ]);
        }
    }

    /**
     * Find a usable invitation for the plain token, or null if it is malformed, unknown, expired or used.
     *
     * The caller must not reveal which of those cases happened.
     */
    public function buscarVigente(string $token): ?Invitacion
    {
        if (! preg_match('/\A[A-Za-z0-9]{40,128}\z/', $token)) {
            return null;
        }

        $hash = self::hashear($token);

        $invitacion = $this->consulta()
            ->with('restaurante')
            ->where('token', $hash)
            ->whereNull('aceptada_en')
            ->where('expira_en', '>', now())
            ->first();

        if ($invitacion === null || ! hash_equals($invitacion->token, $hash)) {
            return null;
        }

        return $invitacion->restaurante?->isActivo() ? $invitacion : null;
    }

    /**
     * Consume the invitation (single use) and link the user to the restaurant with the invited role.
     *
     * Pass an existing user, or the name and password of the account to create. Returns null when the
     * invitation was already consumed or expired in the meantime.
     */
    public function aceptar(Invitacion $invitacion, ?User $usuario, ?string $nombre = null, ?string $password = null): ?User
    {
        return DB::transaction(function () use ($invitacion, $usuario, $nombre, $password): ?User {
            $consumida = $this->consulta()
                ->whereKey($invitacion->getKey())
                ->whereNull('aceptada_en')
                ->where('expira_en', '>', now())
                ->update(['aceptada_en' => now()]);

            if ($consumida !== 1) {
                return null;
            }

            $usuario ??= User::create([
                'name' => $nombre,
                'email' => $invitacion->email,
                'password' => $password,
                'activo' => true,
            ])->forceFill(['email_verified_at' => now()]);

            $usuario->save();

            $usuario->restaurantes()->syncWithoutDetaching([
                $invitacion->restaurante_id => ['rol' => $invitacion->rol],
            ]);
            $usuario->olvidarRoles();

            return $usuario;
        });
    }

    /**
     * SHA-256 hash stored in invitaciones.token.
     */
    public static function hashear(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The token is the credential, so lookups are explicitly independent of the active restaurant.
     *
     * @return Builder<Invitacion>
     */
    private function consulta(): Builder
    {
        return Invitacion::query()->withoutGlobalScope(RestauranteScope::class);
    }
}
