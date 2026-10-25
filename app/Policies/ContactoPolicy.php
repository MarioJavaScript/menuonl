<?php

namespace App\Policies;

use App\Enums\Capacidad;
use App\Models\Contacto;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Contacts: the editor manages them too (RF-18). Under a plan that only edits phones (Sitio y menú), the
 * restaurant's team works with call and WhatsApp contacts only; the other types need the data capability.
 */
class ContactoPolicy extends MatrizPolicy
{
    /**
     * Contact types that count as phones for the plan (docs/planes.md, section 1.4).
     *
     * @var list<string>
     */
    public const array TIPOS_DE_TELEFONO = ['telefono', 'whatsapp'];

    protected const ?Capacidad CAPACIDAD = Capacidad::EditarTelefonos;

    protected const array PERMISOS = [
        'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'propietario' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
        'editor' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'reorder'],
    ];

    /**
     * Contact types the user can see and change in the restaurant; null means all of them.
     *
     * @return list<string>|null
     */
    public function tiposPermitidos(User $user, ?int $restauranteId): ?array
    {
        if ($user->isSuperadmin() || $this->planIncluye($restauranteId, Capacidad::EditarDatos)) {
            return null;
        }

        return self::TIPOS_DE_TELEFONO;
    }

    protected function permiteSobre(User $user, string $habilidad, Model $modelo): bool
    {
        if (! parent::permiteSobre($user, $habilidad, $modelo)) {
            return false;
        }

        $tipos = $this->tiposPermitidos($user, $this->restauranteDe($modelo));

        return $tipos === null || ($modelo instanceof Contacto && in_array($modelo->tipo, $tipos, true));
    }
}
