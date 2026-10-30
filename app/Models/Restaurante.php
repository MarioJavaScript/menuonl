<?php

namespace App\Models;

use App\Enums\Capacidad;
use App\Enums\MotivoDePausa;
use App\Enums\PlanRestaurante;
use App\Services\Catalogos;
use App\Traits\Auditable;
use App\Traits\ProtegeImagenesDelRestaurante;
use Carbon\Carbon;
use Filament\Models\Contracts\HasName;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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
        'plan' => 'completo',
        'tema' => 'calido',
        'sitio_plantilla' => 'a',
        'reservas_activas' => true,
        'reservas_cupo' => 30,
        'reservas_franja_min' => 60,
    ];

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'eslogan',
        'logo',
        'portada',
        'tema',
        'color_acento',
        'sitio_plantilla',
        'latitud',
        'longitud',
        'reservas_activas',
        'reservas_cupo',
        'reservas_franja_min',
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
            'reservas_activas' => 'boolean',
            'reservas_cupo' => 'integer',
            'reservas_franja_min' => 'integer',
            'latitud' => 'float',
            'longitud' => 'float',
        ];
    }

    /**
     * Plan de venta. Al leer, un valor raro de la base cae al plan más restrictivo (D12); al guardar,
     * solo se aceptan los valores del enum. No está en $fillable: se cambia con forceFill desde Plataforma.
     *
     * @return Attribute<PlanRestaurante, PlanRestaurante|string>
     */
    protected function plan(): Attribute
    {
        return Attribute::make(
            get: fn (?string $valor): PlanRestaurante => PlanRestaurante::desde($valor),
            set: fn (PlanRestaurante|string $valor): string => ($valor instanceof PlanRestaurante ? $valor : PlanRestaurante::from($valor))->value,
        );
    }

    /**
     * Cambia el plan y lo deja en el historial (quién, cuándo, plan anterior y nuevo). Es la única vía de escritura
     * del plan: la política `cambiarPlan` (solo superadmin) se comprueba aquí, no solo en el formulario.
     * El guardado va sin eventos para que el historial lleve al usuario que se pasa, no al de la sesión, y sin duplicados.
     *
     * @throws AuthorizationException
     */
    public function cambiarPlan(PlanRestaurante $nuevo, User $quien): bool
    {
        Gate::forUser($quien)->authorize('cambiarPlan', $this);

        $anterior = $this->plan;

        if ($anterior === $nuevo) {
            return false;
        }

        DB::transaction(function () use ($nuevo, $anterior, $quien): void {
            self::withoutEvents(fn () => $this->forceFill(['plan' => $nuevo])->save());

            HistorialCambio::withoutGlobalScopes()->create([
                'restaurante_id' => $this->getKey(),
                'usuario_id' => $quien->getKey(),
                'entidad' => $this->getAuditEntityName(),
                'entidad_id' => (int) $this->getKey(),
                'accion' => 'actualizar',
                'cambios' => ['plan' => ['antes' => $anterior->value, 'despues' => $nuevo->value]],
            ]);
        });

        $this->triggerMenuCachePurge();

        return true;
    }

    public function incluye(Capacidad $capacidad): bool
    {
        return $this->plan->incluye($capacidad);
    }

    /**
     * Motivo por el que el restaurante está pausado para su equipo, o null si no lo está. Único lugar donde se decide:
     * un motivo nuevo se agrega aquí y el acceso al panel no cambia.
     */
    public function motivoDePausa(): ?MotivoDePausa
    {
        return $this->isActivo() ? null : MotivoDePausa::FueraDeServicio;
    }

    public function estaPausado(): bool
    {
        return $this->motivoDePausa() !== null;
    }

    /**
     * Si el equipo (todo el que no es superadmin) puede usar el panel de este restaurante: el plan incluye el panel
     * y el restaurante no está pausado. Regla única del panel, de la lista de restaurantes y de la descarga de QR.
     */
    public function permiteElPanelAlEquipo(): bool
    {
        return ! $this->estaPausado() && $this->incluye(Capacidad::Panel);
    }

    /**
     * El superadmin edita por encima del techo del plan; los demás, solo lo que el plan incluye.
     */
    public function permiteA(User $usuario, Capacidad $capacidad): bool
    {
        return $usuario->isSuperadmin() || $this->incluye($capacidad);
    }

    public function tieneSitio(): bool
    {
        return $this->incluye(Capacidad::Sitio);
    }

    public function reservasDisponibles(): bool
    {
        return (bool) $this->reservas_activas && $this->incluye(Capacidad::Reservas);
    }

    /**
     * Tema vigente de la página pública; cualquier valor desconocido cae al tema por defecto.
     */
    public function temaVigente(): string
    {
        return array_key_exists((string) $this->tema, Catalogos::temas()) ? (string) $this->tema : 'calido';
    }

    /**
     * Color de acento listo para imprimir en un atributo style: solo hex de 6 dígitos, nunca texto libre.
     */
    public function colorAcentoSeguro(): ?string
    {
        return preg_match('/\A#[0-9a-fA-F]{6}\z/', (string) $this->color_acento) === 1 ? (string) $this->color_acento : null;
    }

    /**
     * Color de texto legible sobre el acento personalizado (blanco u oscuro según su luminosidad).
     */
    public function colorAcentoTexto(): ?string
    {
        $hex = $this->colorAcentoSeguro();

        if ($hex === null) {
            return null;
        }

        [$r, $g, $b] = array_map(
            fn (string $par): float => hexdec($par) / 255,
            str_split(ltrim($hex, '#'), 2)
        );
        $lineal = fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminancia = 0.2126 * $lineal($r) + 0.7152 * $lineal($g) + 0.0722 * $lineal($b);

        return $luminancia > 0.4 ? '#17171e' : '#ffffff';
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
     * Número de "WhatsApp para pedidos" solo con dígitos, o null si no tiene de 8 a 20 (la misma regla del formulario del panel).
     */
    public function whatsappPedidosDigitos(): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $this->whatsapp_pedidos);

        return is_string($digitos) && preg_match('/\A[0-9]{8,20}\z/', $digitos) === 1 ? $digitos : null;
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

    /**
     * Translation fallback for Restaurante, which holds its nombre and descripcion directly.
     */
    public function traduccion(?string $idioma = null): self
    {
        return $this;
    }
}
