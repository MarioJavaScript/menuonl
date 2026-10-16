<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasCurrentTenantLabel;
use Filament\Models\Contracts\HasName;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Restaurante extends Model implements HasAvatar, HasCurrentTenantLabel, HasName
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'restaurantes';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'logo',
        'portada',
        'moneda',
        'idioma_default',
        'zona_horaria',
        'whatsapp_pedidos',
        'pedidos_activos',
        'mostrar_agotados',
        'estado',
    ];

    protected $attributes = [
        'estado' => 'activo',
        'pedidos_activos' => false,
        'mostrar_agotados' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pedidos_activos' => 'boolean',
            'mostrar_agotados' => 'boolean',
        ];
    }

    /**
     * Users associated with this restaurant.
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'restaurante_usuario')
            ->withPivot('rol')
            ->withTimestamps();
    }

    /**
     * Active configured languages.
     */
    public function idiomas(): HasMany
    {
        return $this->hasMany(RestauranteIdioma::class, 'restaurante_id');
    }

    /**
     * Alias for idiomas.
     */
    public function idioma(): HasMany
    {
        return $this->idiomas();
    }

    /**
     * Public contact channels.
     */
    public function contactos(): HasMany
    {
        return $this->hasMany(Contacto::class, 'restaurante_id');
    }

    /**
     * Operating hours and shifts.
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'restaurante_id');
    }

    /**
     * Menu categories.
     */
    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class, 'restaurante_id');
    }

    /**
     * Dishes and products.
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'restaurante_id');
    }

    /**
     * QR code access records.
     */
    public function codigosQr(): HasMany
    {
        return $this->hasMany(CodigoQr::class, 'restaurante_id');
    }

    /**
     * Alias for codigosQr.
     */
    public function codigos(): HasMany
    {
        return $this->codigosQr();
    }

    /**
     * Relationship alias for Filament's default pluralization convention (CodigoQr -> codigoQrs).
     */
    public function codigoQrs(): HasMany
    {
        return $this->codigosQr();
    }

    /**
     * Outstanding user invitations.
     */
    public function invitaciones(): HasMany
    {
        return $this->hasMany(Invitacion::class, 'restaurante_id');
    }

    /**
     * Relationship alias for Filament's default pluralization convention (Invitacion -> invitacions).
     */
    public function invitacions(): HasMany
    {
        return $this->invitaciones();
    }

    /**
     * Filament multi-tenancy tenant display name.
     */
    public function getFilamentName(): string|Htmlable
    {
        return (string) $this->nombre;
    }

    /**
     * Filament multi-tenancy tenant avatar / logo URL.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->logo ? Storage::url($this->logo) : null;
    }

    /**
     * Filament multi-tenancy current tenant badge label.
     */
    public function getCurrentTenantLabel(): string
    {
        return __('Restaurante');
    }

    /**
     * Virtual name attribute accessor for generic property access.
     */
    public function getNameAttribute(): string
    {
        return (string) $this->nombre;
    }

    /**
     * Forensic audit trail records.
     */
    public function historialCambios(): HasMany
    {
        return $this->hasMany(HistorialCambio::class, 'restaurante_id');
    }

    /**
     * Check if restaurant is active.
     */
    public function isActivo(): bool
    {
        return ($this->estado ?? 'activo') === 'activo';
    }

    /**
     * Dynamically calculate whether the restaurant is currently open.
     *
     * Supports several shifts per day and overnight shifts (cierra <= abre closes the next day).
     * A day flagged as `cerrado` ignores all of that day's shifts, including its overnight spill.
     */
    public function estaAbiertoAhora(?CarbonInterface $now = null): bool
    {
        if (! $this->isActivo()) {
            return false;
        }

        $zona = $this->zona_horaria ?: 'America/Mexico_City';

        try {
            $ahora = $now ? Carbon::instance($now)->copy()->setTimezone($zona) : Carbon::now($zona);
        } catch (\Throwable) {
            $ahora = $now ? Carbon::instance($now)->copy() : Carbon::now();
        }

        $hora = $ahora->format('H:i:s');
        $hoy = $ahora->dayOfWeek; // 0 = Sunday ... 6 = Saturday
        $ayer = ($hoy + 6) % 7;

        $horarios = $this->relationLoaded('horarios') ? $this->horarios : $this->horarios()->get();
        $diasCerrados = $horarios->where('cerrado', true)->pluck('dia_semana');

        $normalize = function (?string $time): ?string {
            if (! $time) {
                return null;
            }

            return strlen($time) === 5 ? "{$time}:00" : $time;
        };

        foreach ($horarios as $horario) {
            $abre = $normalize($horario->abre);
            $cierra = $normalize($horario->cierra);

            if ($abre === null || $cierra === null || $diasCerrados->contains($horario->dia_semana)) {
                continue;
            }

            $esNocturno = $cierra <= $abre;

            if ($horario->dia_semana === $hoy && $hora >= $abre && ($esNocturno || $hora < $cierra)) {
                return true;
            }

            if ($horario->dia_semana === $ayer && $esNocturno && $hora < $cierra) {
                return true;
            }
        }

        return false;
    }
}
