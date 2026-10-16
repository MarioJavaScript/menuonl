<?php

namespace App\Models;

use App\Models\Scopes\RestauranteScope;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'es_superadmin',
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
        return (bool) $this->activo;
    }

    /**
     * Determine which restaurants this user can access in the panel.
     */
    public function getTenants(Panel $panel): array|Collection
    {
        if ($this->isSuperadmin()) {
            return Restaurante::all();
        }

        return $this->restaurantes()->where('restaurantes.estado', 'activo')->get();
    }

    /**
     * Determine if user can access a specific tenant restaurant.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        if (! $tenant instanceof Restaurante) {
            return false;
        }

        return $this->restaurantes()
            ->where('restaurantes.id', $tenant->id)
            ->where('restaurantes.estado', 'activo')
            ->exists();
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
     * Check if user is superadmin.
     */
    public function isSuperadmin(): bool
    {
        return (bool) $this->es_superadmin;
    }

    /**
     * Alias for isSuperadmin.
     */
    public function esSuperadmin(): bool
    {
        return $this->isSuperadmin();
    }

    /**
     * Retrieve the pivot role of this user in the specified restaurant.
     */
    public function rolEn(Restaurante $r): ?string
    {
        if ($this->relationLoaded('restaurantes')) {
            $restaurant = $this->restaurantes->firstWhere('id', $r->id);
            if ($restaurant?->pivot?->rol) {
                return $restaurant->pivot->rol;
            }
        }

        return $this->restaurantes()
            ->where('restaurantes.id', $r->id)
            ->first()?->pivot?->rol;
    }

    /**
     * Check if user is propietario of the given restaurant.
     */
    public function esPropietarioDe(Restaurante $r): bool
    {
        return $this->rolEn($r) === 'propietario';
    }

    /**
     * Check if user is editor of the given restaurant.
     */
    public function esEditorDe(Restaurante $r): bool
    {
        return $this->rolEn($r) === 'editor';
    }

    /**
     * Retrieve role in a specific restaurant or current scoped restaurant context.
     */
    public function rolEnRestaurante(?Restaurante $restaurante = null): ?string
    {
        if ($restaurante) {
            return $this->rolEn($restaurante);
        }

        $restauranteId = RestauranteScope::getRestauranteId();
        if (! $restauranteId) {
            return null;
        }

        if ($this->relationLoaded('restaurantes')) {
            $matched = $this->restaurantes->firstWhere('id', $restauranteId);
            if ($matched?->pivot?->rol) {
                return $matched->pivot->rol;
            }
        }

        return $this->restaurantes()
            ->where('restaurantes.id', $restauranteId)
            ->first()?->pivot?->rol;
    }

    /**
     * Check if user has propietario capabilities (in current context or specific restaurant).
     */
    public function esPropietario(?Restaurante $restaurante = null): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        if ($restaurante) {
            return $this->esPropietarioDe($restaurante);
        }

        $restauranteId = RestauranteScope::getRestauranteId();
        if ($restauranteId) {
            return $this->rolEnRestaurante() === 'propietario';
        }

        return $this->restaurantes()
            ->where('restaurantes.estado', 'activo')
            ->wherePivot('rol', 'propietario')
            ->exists();
    }

    /**
     * Check if user has editor (or higher) capabilities (in current context or specific restaurant).
     */
    public function esEditor(?Restaurante $restaurante = null): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        if ($restaurante) {
            $rol = $this->rolEn($restaurante);

            return $rol === 'propietario' || $rol === 'editor';
        }

        $restauranteId = RestauranteScope::getRestauranteId();
        if ($restauranteId) {
            $rol = $this->rolEnRestaurante();

            return $rol === 'propietario' || $rol === 'editor';
        }

        return $this->restaurantes()
            ->where('restaurantes.estado', 'activo')
            ->whereIn('restaurante_usuario.rol', ['propietario', 'editor'])
            ->exists();
    }
}
