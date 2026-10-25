<?php

namespace App\Services;

use App\Enums\Capacidad;
use App\Enums\PlanRestaurante;
use App\Models\Restaurante;
use App\Models\User;

/**
 * Which modules of the service panel (/panel) each restaurant role can use.
 *
 * The panel hides the others and the API answers 403 to them (see ModuloPanel).
 * The restaurant's plan is a ceiling over the role: modules of the role ∩ modules of the plan (docs/planes.md, 2.4).
 */
class PermisosPanel
{
    public const array MODULOS = ['inicio', 'reservas', 'menu', 'pedidos', 'ajustes'];

    /**
     * @var array<string, list<string>>
     */
    private const array MODULOS_POR_ROL = [
        'propietario' => self::MODULOS,
        'gerente' => self::MODULOS,
        'editor' => ['inicio', 'reservas', 'menu', 'pedidos'],
        'recepcion' => ['inicio', 'reservas'],
        'cocina' => ['pedidos'],
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
     * Roles a team manager (propietario or gerente) can assign; only a propietario or superadmin hands out "propietario".
     *
     * @return list<string>
     */
    public static function rolesAsignables(User $usuario, int $restauranteId): array
    {
        $esPropietario = $usuario->isSuperadmin() || $usuario->rolEn($restauranteId) === 'propietario';

        return $esPropietario
            ? ['propietario', 'gerente', 'editor', 'recepcion', 'cocina']
            : ['gerente', 'editor', 'recepcion', 'cocina'];
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
