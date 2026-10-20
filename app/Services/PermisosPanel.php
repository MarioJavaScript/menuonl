<?php

namespace App\Services;

use App\Models\User;

/**
 * Which modules of the service panel (/panel) each restaurant role can use.
 *
 * The panel hides the others and the API answers 403 to them (see ModuloPanel).
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
    public static function modulosDe(User $usuario, int $restauranteId): array
    {
        if ($usuario->isSuperadmin()) {
            return self::MODULOS;
        }

        return self::MODULOS_POR_ROL[(string) $usuario->rolEn($restauranteId)] ?? [];
    }

    public static function permite(User $usuario, int $restauranteId, string $modulo): bool
    {
        return in_array($modulo, self::modulosDe($usuario, $restauranteId), true);
    }
}
