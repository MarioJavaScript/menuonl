<?php

namespace App\Models;

use App\Enums\Capacidad;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasTenants
{
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    /**
     * Memoized restaurante_id => rol map for this instance.
     *
     * @var array<int, string>|null
     */
    protected ?array $rolesPorRestaurante = null;

    protected $fillable = [
        'name',
        'email',
        'password',
        'activo',
        'ultimo_acceso',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso' => 'datetime',
            'password' => 'hashed',
            'es_superadmin' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * Determine if user can access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->activo) {
            return false;
        }

        return $this->isSuperadmin() || $this->restaurantesConPanel()->isNotEmpty();
    }

    /**
     * Whether this user should be shown the "your plan has no panel" screen instead of a bare 403: an active,
     * non-superadmin account whose only active memberships are restaurants whose plan leaves the panel closed.
     * Someone with at least one restaurant that does have the panel goes straight in, and never sees the screen.
     */
    public function debeVerElAvisoDePlanSinPanel(): bool
    {
        return $this->activo
            && ! $this->isSuperadmin()
            && $this->restaurantesConPanel()->isEmpty()
            && $this->restaurantesSinPanelPorPlan()->isNotEmpty();
    }

    /**
     * Active memberships in restaurants whose plan does not include the panel, by name for the notice.
     *
     * @return Collection<int, Restaurante>
     */
    public function restaurantesSinPanelPorPlan(): Collection
    {
        return $this->restaurantes()->wherePivot('activo', true)->get()
            ->reject(fn (Restaurante $restaurante): bool => $restaurante->incluye(Capacidad::Panel))
            ->sortBy('nombre')
            ->values();
    }

    /**
     * Active restaurants whose plan lets their team into the panel (Solo menú does not).
     *
     * @return Collection<int, Restaurante>
     */
    private function restaurantesConPanel(): Collection
    {
        return $this->restaurantes()->wherePivot('activo', true)->get()
            ->filter(fn (Restaurante $restaurante): bool => $restaurante->incluye(Capacidad::Panel))
            ->values();
    }

    /**
     * Restaurants associated with this user.
     */
    public function restaurantes(): BelongsToMany
    {
        return $this->belongsToMany(Restaurante::class, 'restaurante_usuario')
            ->withPivot('rol')
            ->withTimestamps();
    }

    /**
     * Restaurants the user can pick as the panel tenant.
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->isSuperadmin() ? Restaurante::all() : $this->restaurantesConPanel();
    }

    /**
     * Determine if the user can administer the given restaurant.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Restaurante) {
            return false;
        }

        return $this->isSuperadmin()
            || ($tenant->incluye(Capacidad::Panel) && $this->restaurantes()->wherePivot('activo', true)->whereKey($tenant->getKey())->exists());
    }

    /**
     * Role of the user in the given restaurant ('propietario' or 'editor'), or null when not a member.
     *
     * The superadmin flag is not a pivot role, so it never appears here.
     */
    public function rolEn(Restaurante|int|null $restaurante): ?string
    {
        if ($restaurante === null) {
            return null;
        }

        $this->rolesPorRestaurante ??= DB::table('restaurante_usuario')
            ->where('user_id', $this->getKey())
            ->where('activo', true)
            ->pluck('rol', 'restaurante_id')
            ->all();

        return $this->rolesPorRestaurante[$restaurante instanceof Restaurante ? $restaurante->getKey() : $restaurante] ?? null;
    }

    /**
     * Whether the user is a propietario of the given restaurant.
     */
    public function esPropietarioDe(Restaurante|int|null $restaurante): bool
    {
        return $this->rolEn($restaurante) === 'propietario';
    }

    /**
     * Whether the user is an editor of the given restaurant.
     */
    public function esEditorDe(Restaurante|int|null $restaurante): bool
    {
        return $this->rolEn($restaurante) === 'editor';
    }

    /**
     * Whether this user must use two-factor authentication: superadmins and propietarios of any restaurant.
     *
     * Editors only are not required to (they may still enable it).
     */
    public function requiereDosFactores(): bool
    {
        return $this->isSuperadmin()
            || $this->restaurantes()->wherePivot('rol', 'propietario')->exists();
    }

    /**
     * Whether the user only holds the operational role (recepcion) and is not a superadmin,
     * so their home is the Reservas page of /admin (see LoginRespuesta).
     */
    public function esSoloOperativo(): bool
    {
        if ($this->isSuperadmin()) {
            return false;
        }

        $roles = DB::table('restaurante_usuario')->where('user_id', $this->getKey())->where('activo', true)->pluck('rol');

        return $roles->isNotEmpty() && $roles->every(fn (string $rol): bool => $rol === 'recepcion');
    }

    /**
     * Forget the memoized roles after changing the restaurant memberships.
     */
    public function olvidarRoles(): void
    {
        $this->rolesPorRestaurante = null;
    }

    /**
     * Check if user is superadmin.
     */
    public function isSuperadmin(): bool
    {
        return (bool) $this->es_superadmin;
    }
}
