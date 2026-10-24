<?php

namespace App\Policies;

use App\Enums\Capacidad;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Policies\Concerns\ConsultaElPlanDelRestaurante;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy driven by a permission matrix (level => allowed abilities).
 *
 * Levels are 'superadmin' (platform flag) and the pivot roles 'propietario' and 'editor'.
 * A user who is not a member of the restaurant in question has no level and is always denied.
 * When CAPACIDAD is set, the restaurant's plan is a ceiling: everyone but the superadmin is denied
 * if the plan does not include that capability (docs/planes.md, 2.3).
 */
abstract class MatrizPolicy
{
    use ConsultaElPlanDelRestaurante;

    /**
     * Abilities granted to each level. A missing level or ability means denied.
     *
     * @var array<string, list<string>>
     */
    protected const array PERMISOS = [];

    /**
     * Capability the restaurant's plan must include for the abilities of this policy; null means no plan ceiling.
     */
    protected const ?Capacidad CAPACIDAD = null;

    /**
     * Abilities that bulk actions map onto a single-record ability.
     *
     * @var array<string, string>
     */
    private const array ALIAS_MASIVOS = [
        'deleteAny' => 'delete',
        'restoreAny' => 'restore',
        'forceDeleteAny' => 'forceDelete',
    ];

    /**
     * Restaurant that owns the record. Child models override this to authorize through their parent.
     */
    protected function restauranteDe(Model $modelo): ?int
    {
        return $modelo->restaurante_id === null ? null : (int) $modelo->restaurante_id;
    }

    /**
     * Whether the matrix grants the ability to the user in the given restaurant.
     */
    public function permite(User $user, string $habilidad, ?int $restauranteId): bool
    {
        $nivel = $user->isSuperadmin() ? 'superadmin' : $user->rolEn($restauranteId);

        if ($nivel === null) {
            return false;
        }

        if ($nivel !== 'superadmin' && ! $this->planIncluyeCapacidad($restauranteId)) {
            return false;
        }

        return in_array($habilidad, static::PERMISOS[$nivel] ?? [], true);
    }

    /**
     * Whether the plan of the restaurant includes the capability this policy depends on.
     */
    protected function planIncluyeCapacidad(?int $restauranteId): bool
    {
        if (static::CAPACIDAD === null) {
            return true;
        }

        return $this->planIncluye($restauranteId, static::CAPACIDAD);
    }

    /**
     * Abilities without a record (listing, creating, bulk actions) are evaluated against the active restaurant.
     */
    protected function permiteEnRestauranteActivo(User $user, string $habilidad): bool
    {
        $habilidad = self::ALIAS_MASIVOS[$habilidad] ?? $habilidad;

        return $this->permite($user, $habilidad, RestauranteScope::resolveRestauranteId());
    }

    protected function permiteSobre(User $user, string $habilidad, Model $modelo): bool
    {
        return $this->permite($user, $habilidad, $this->restauranteDe($modelo));
    }

    public function viewAny(User $user): bool
    {
        return $this->permiteEnRestauranteActivo($user, 'viewAny');
    }

    public function view(User $user, Model $modelo): bool
    {
        return $this->permiteSobre($user, 'view', $modelo);
    }

    public function create(User $user): bool
    {
        return $this->permiteEnRestauranteActivo($user, 'create');
    }

    public function update(User $user, Model $modelo): bool
    {
        return $this->permiteSobre($user, 'update', $modelo);
    }

    public function delete(User $user, Model $modelo): bool
    {
        return $this->permiteSobre($user, 'delete', $modelo);
    }

    public function deleteAny(User $user): bool
    {
        return $this->permiteEnRestauranteActivo($user, 'deleteAny');
    }

    public function restore(User $user, Model $modelo): bool
    {
        return $this->permiteSobre($user, 'restore', $modelo);
    }

    public function restoreAny(User $user): bool
    {
        return $this->permiteEnRestauranteActivo($user, 'restoreAny');
    }

    public function forceDelete(User $user, Model $modelo): bool
    {
        return $this->permiteSobre($user, 'forceDelete', $modelo);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->permiteEnRestauranteActivo($user, 'forceDeleteAny');
    }

    public function reorder(User $user): bool
    {
        return $this->permiteEnRestauranteActivo($user, 'reorder');
    }
}
