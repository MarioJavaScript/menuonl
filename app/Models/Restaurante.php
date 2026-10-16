<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\ProtegeImagenesDelRestaurante;
use Carbon\Carbon;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Restaurante extends Model implements HasName
{
    use Auditable, HasFactory, ProtegeImagenesDelRestaurante, SoftDeletes;

    protected $table = 'restaurantes';

    /**
     * Mirror of the column defaults, so freshly created models can resolve
     * the time zone, default language and state without a refresh.
     */
    protected $attributes = [
        'moneda' => 'MXN',
        'idioma_default' => 'es',
        'zona_horaria' => 'America/Mexico_City',
        'pedidos_activos' => false,
        'mostrar_agotados' => true,
        'estado' => 'activo',
    ];

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

    /**
     * Image columns guarded by ProtegeImagenesDelRestaurante.
     *
     * @return array<int, string>
     */
    public function columnasDeImagen(): array
    {
        return ['logo', 'portada'];
    }

    public function restauranteIdDeImagenes(): ?int
    {
        return $this->exists ? (int) $this->getKey() : null;
    }

    protected function casts(): array
    {
        return [
            'pedidos_activos' => 'boolean',
            'mostrar_agotados' => 'boolean',
        ];
    }

    /**
     * Name shown in the panel's restaurant switcher (the column is `nombre`, not `name`).
     */
    public function getFilamentName(): string
    {
        return $this->nombre;
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
     * Languages the public menu is published in.
     */
    public function idiomas(): HasMany
    {
        return $this->hasMany(RestauranteIdioma::class);
    }

    /**
     * Menu categories.
     */
    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class);
    }

    /**
     * Menu products (dishes).
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    /**
     * Contact entries (phone, WhatsApp, address, social networks).
     */
    public function contactos(): HasMany
    {
        return $this->hasMany(Contacto::class);
    }

    /**
     * Opening hours; several rows per day mean several shifts.
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    /**
     * QR codes (general and per table).
     */
    public function codigosQr(): HasMany
    {
        return $this->hasMany(CodigoQr::class);
    }

    /**
     * Pending and accepted user invitations.
     */
    public function invitaciones(): HasMany
    {
        return $this->hasMany(Invitacion::class);
    }

    /**
     * Audit log of panel changes.
     */
    public function historialCambios(): HasMany
    {
        return $this->hasMany(HistorialCambio::class);
    }

    /**
     * Check if restaurant is active.
     */
    public function isActivo(): bool
    {
        return $this->estado === 'activo';
    }

    /**
     * Determine if the restaurant is open at the given moment, in its own time zone.
     *
     * Supports several shifts per day and overnight shifts (cierra <= abre closes the next day).
     * A day flagged as `cerrado` ignores all of that day's shifts, including its overnight spill.
     */
    public function estaAbiertoAhora(?Carbon $now = null): bool
    {
        $ahora = ($now ?? now())->copy()->setTimezone($this->zona_horaria);
        $hora = $ahora->format('H:i:s');
        $hoy = $ahora->dayOfWeek;
        $ayer = ($hoy + 6) % 7;

        $diasCerrados = $this->horarios->where('cerrado', true)->pluck('dia_semana');

        foreach ($this->horarios as $horario) {
            if ($horario->abre === null || $horario->cierra === null || $diasCerrados->contains($horario->dia_semana)) {
                continue;
            }

            $esNocturno = $horario->cierra <= $horario->abre;

            if ($horario->dia_semana === $hoy && $hora >= $horario->abre && ($esNocturno || $hora < $horario->cierra)) {
                return true;
            }

            if ($horario->dia_semana === $ayer && $esNocturno && $hora < $horario->cierra) {
                return true;
            }
        }

        return false;
    }
}
