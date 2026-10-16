<?php

namespace App\Services;

use App\Mail\InvitacionMail;
use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
     * Earlier pending invitations for the same email and restaurant are replaced. The mail is sent
     * synchronously on purpose: queueing it would write the plain token into the jobs table.
     *
     * @throws AuthorizationException when the inviter is neither superadmin nor propietario of the restaurant
     */
    public function invitar(Restaurante $restaurante, string $email, string $rol, ?User $invitadoPor): Invitacion
    {
        if ($invitadoPor !== null && ! $invitadoPor->isSuperadmin() && ! $invitadoPor->esPropietarioDe($restaurante)) {
            throw new AuthorizationException('Solo un propietario puede invitar usuarios.');
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

        Mail::to($email)->send(new InvitacionMail($invitacion, $restaurante, $token));

        return $invitacion;
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
