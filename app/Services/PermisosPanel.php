<?php

namespace App\Services;

use App\Enums\Capacidad;
use App\Enums\PlanRestaurante;
use App\Models\Restaurante;
use App\Models\User;

/**
 * Which modules of the panel (/admin) each restaurant role can use: Reservas, Invitaciones, el inicio de sesión y GestionDeReservas la consultan.
 *
 * The restaurant's plan is a ceiling over the role: modules of the role ∩ modules of the plan (docs/planes.md, 2.4).
 */
class PermisosPanel
{
    public const array MODULOS = ['inicio', 'reservas', 'menu', 'ajustes'];

    /**
     * @var array<string, list<string>>
     */
    private const array MODULOS_POR_ROL = [
        'propietario' => self::MODULOS,
        'gerente' => self::MODULOS,
        'editor' => ['inicio', 'reservas', 'menu'],
        'recepcion' => ['inicio', 'reservas'],
    ];

    /**
     * Modules each plan leaves open to anyone but the superadmin. Sitio y menú keeps only the reservations (D2).
     *
     * @return list<string>
     */
    public static function modulosDelPlan(PlanRestaurante $plan): array
    {
        return match ($plan) {
            PlanRestaurante::Completo => self::MODULOS,
            PlanRestaurante::SitioMenu => ['reservas'],
            PlanRestaurante::SoloMenu => [],
        };
    }

    /**
     * Plan of the restaurant, read fresh on every call. A missing restaurant counts as the most restrictive plan.
     */
    private static function planDe(int $restauranteId): PlanRestaurante
    {
        return Restaurante::query()->whereKey($restauranteId)->first(['id', 'plan'])?->plan ?? PlanRestaurante::SoloMenu;
    }

    /**
     * Roles offered when inviting or changing a role: the only switch to offer more of them again. The other roles stay
     * in the code (modules, policies, existing members) but cannot be handed out to anyone new.
     *
     * @var list<string>
     */
    public const array ROLES_OFRECIDOS = ['propietario'];

    /**
     * Roles a team manager (propietario or gerente) can hand out now: those they may manage that are also offered.
     * A gerente cannot hand out "propietario", so for them the list is empty.
     *
     * @return list<string>
     */
    public static function rolesAsignables(User $usuario, int $restauranteId): array
    {
        return array_values(array_intersect(self::rolesModificables($usuario, $restauranteId), self::ROLES_OFRECIDOS));
    }

    /**
     * Roles whose members a team manager can manage (deactivate, change): only a propietario or superadmin touches a
     * "propietario". Members keep these roles whether or not they are still offered.
     *
     * @return list<string>
     */
    public static function rolesModificables(User $usuario, int $restauranteId): array
    {
        $esPropietario = $usuario->isSuperadmin() || $usuario->rolEn($restauranteId) === 'propietario';

        return $esPropietario
            ? ['propietario', 'gerente', 'editor', 'recepcion']
            : ['gerente', 'editor', 'recepcion'];
    }

    /**
     * @return list<string>
     */
    public static function modulosDe(User $usuario, Restaurante|int $restaurante): array
    {
        $restauranteId = $restaurante instanceof Restaurante ? (int) $restaurante->getKey() : $restaurante;
        $plan = $restaurante instanceof Restaurante ? $restaurante->plan : self::planDe($restauranteId);

        if ($usuario->isSuperadmin()) {
            // Edits above the ceiling, but a plan without reservations has no reservations section for anyone.
            return $plan->incluye(Capacidad::Reservas) ? self::MODULOS : array_values(array_diff(self::MODULOS, ['reservas']));
        }

        $delRol = self::MODULOS_POR_ROL[(string) $usuario->rolEn($restauranteId)] ?? [];

        return array_values(array_intersect($delRol, self::modulosDelPlan($plan)));
    }

    public static function permite(User $usuario, Restaurante|int $restaurante, string $modulo): bool
    {
        return in_array($modulo, self::modulosDe($usuario, $restaurante), true);
    }
}
