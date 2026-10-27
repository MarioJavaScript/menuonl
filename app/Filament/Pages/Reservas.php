<?php

namespace App\Filament\Pages;

use App\Models\Reserva;
use App\Models\ReservaEvento;
use App\Models\Restaurante;
use App\Models\User;
use App\Services\ConflictoDeReserva;
use App\Services\FormatoFecha;
use App\Services\GestionDeReservas;
use App\Services\PermisosPanel;
use App\Services\ReservasDelDia;
use BackedEnum;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/**
 * Reservas del servicio dentro de /admin: Próximas, Hoy y un día elegido; agregar, confirmar, "Llegó", cancelar,
 * restaurar y mover de hora y fecha (docs/integracion-dashboard.md, sección 18).
 *
 * No tiene lógica de negocio propia. Lee con ReservasDelDia y escribe con GestionDeReservas, que vuelve a comprobar
 * módulo por rol y plan, pertenencia al restaurante, política y validación. Del navegador solo se acepta el id de la
 * reserva (`wire:click="mountAction('cancelar', { reserva: 12 })"`): se busca siempre dentro del restaurante actual y el
 * estado, la hora y la fecha se leen del servidor.
 */
class Reservas extends Page
{
    private const array VISTAS = ['proximas', 'hoy', 'fecha'];

    protected static ?string $slug = 'reservas';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected string $view = 'filament.pages.reservas';

    #[Url(as: 'vista')]
    public string $vista = 'proximas';

    #[Url(as: 'fecha')]
    public ?string $fecha = null;

    /**
     * Reservas por confirmar que la persona ya vio; solo sirve para anunciar las nuevas que trae el sondeo.
     */
    public int $pendientesVistas = 0;

    /**
     * Texto para el lector de pantalla; solo se llena cuando un sondeo trae reservas nuevas por confirmar.
     */
    public string $anuncio = '';

    protected bool $esSondeo = false;

    public static function canAccess(): bool
    {
        $restaurante = Filament::getTenant();
        $usuario = auth()->user();

        return $restaurante instanceof Restaurante
            && $usuario instanceof User
            && PermisosPanel::permite($usuario, $restaurante, 'reservas')
            && $usuario->can('viewAny', Reserva::class);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('panel.grupos.pedidos');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.reservas.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $restaurante = Filament::getTenant();

        if (! static::canAccess() || ! $restaurante instanceof Restaurante) {
            return null;
        }

        $porConfirmar = app(ReservasDelDia::class)->porConfirmarDesdeHoy($restaurante);

        return $porConfirmar > 0 ? (string) $porConfirmar : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $porConfirmar = (int) static::getNavigationBadge();

        return $porConfirmar > 0 ? trans_choice('panel.reservas.insignia', $porConfirmar, ['n' => $porConfirmar]) : null;
    }

    public function getTitle(): string
    {
        return __('panel.reservas.titulo');
    }

    public function getSubheading(): ?string
    {
        return __('panel.reservas.subtitulo');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 404);

        $this->vista = $this->vistaActual();
        $this->fecha = $this->fechaElegida();
    }

    /**
     * Cambia entre Próximas y Hoy; "Ver otro día" se activa al elegir una fecha.
     */
    public function cambiarVista(string $vista): void
    {
        $this->vista = in_array($vista, ['proximas', 'hoy'], true) ? $vista : 'proximas';
        $this->fecha = null;
    }

    public function updatedFecha(): void
    {
        $this->vista = $this->fechaElegida() === null ? 'proximas' : 'fecha';
    }

    /**
     * Sondeo: vuelve a pintar la lista. Con una ventana abierta (agregar, mover, cancelar, historial) no devuelve HTML,
     * así que no pisa lo que la persona está escribiendo. Solo avisa al lector de pantalla si hay más reservas por
     * confirmar que antes.
     *
     * Filament pinta las ventanas como una parcial aparte, por eso el atributo wire:poll de la vista no se entera de que
     * hay una abierta: la decisión se toma aquí, en el servidor.
     */
    public function refrescar(): void
    {
        abort_unless(static::canAccess(), 403);

        if ($this->mountedActions !== []) {
            $this->skipRender();

            return;
        }

        $this->esSondeo = true;
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('crear')
                ->label(__('panel.reservas.acciones.crear'))
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => static::canAccess())
                ->modalHeading(__('panel.reservas.crear.titulo'))
                ->modalSubmitActionLabel(__('panel.reservas.crear.boton'))
                ->fillForm(fn (): array => [
                    'personas' => 2,
                    'fecha' => $this->vistaActual() === 'fecha' && $this->fechaElegida() !== null
                        ? $this->fechaElegida()
                        : app(ReservasDelDia::class)->hoy($this->restaurante()),
                    'hora' => '20:00',
                ])
                ->schema([
                    TextInput::make('nombre')->label(__('panel.reservas.campos.nombre'))->autocomplete(false),
                    TextInput::make('personas')->label(__('panel.reservas.campos.personas'))->type('number')->inputMode('numeric'),
                    TextInput::make('fecha')->label(__('panel.reservas.campos.fecha'))->type('date'),
                    TextInput::make('hora')->label(__('panel.reservas.campos.hora'))->type('time'),
                ])
                ->action(function (array $data): void {
                    $this->ejecutarEnVentana(function () use ($data): void {
                        app(GestionDeReservas::class)->crear($this->usuario(), $this->restaurante(), [
                            'nombre' => $data['nombre'] ?? null,
                            'personas' => $data['personas'] ?? null,
                            'fecha' => $data['fecha'] ?? null,
                            'hora' => $data['hora'] ?? null,
                        ], 'admin');

                        Notification::make()->title(__('panel.reservas.crear.listo'))->success()->send();
                    });
                }),
        ];
    }

    public function confirmarAction(): Action
    {
        return $this->accionDeEstado('confirmar', 'confirmar');
    }

    public function llegoAction(): Action
    {
        return $this->accionDeEstado('llego', 'marcarLlegada');
    }

    public function restaurarAction(): Action
    {
        return $this->accionDeEstado('restaurar', 'restaurar');
    }

    public function cancelarAction(): Action
    {
        return Action::make('cancelar')
            ->label(__('panel.reservas.acciones.cancelar'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('panel.reservas.cancelar.titulo'))
            ->modalDescription(function (array $arguments): string {
                $reserva = $this->reservaDelRestaurante($arguments);

                return $reserva === null
                    ? __('panel.reservas.cancelar.titulo')
                    : __('panel.reservas.cancelar.descripcion', ['nombre' => $reserva->nombre, 'cuando' => $this->cuando($reserva)]);
            })
            ->modalSubmitActionLabel(__('panel.reservas.cancelar.boton'))
            ->modalCancelActionLabel(__('panel.reservas.cancelar.volver'))
            ->action(fn (array $arguments) => $this->ejecutarCambioDeEstado($arguments, 'cancelar', 'panel.reservas.cancelar.listo'));
    }

    public function moverAction(): Action
    {
        return Action::make('mover')
            ->label(__('panel.reservas.acciones.mover'))
            ->modalHeading(function (array $arguments): string {
                $reserva = $this->reservaDelRestaurante($arguments);

                return __('panel.reservas.mover.titulo', ['nombre' => $reserva?->nombre ?? '']);
            })
            ->modalSubmitActionLabel(__('panel.reservas.mover.boton'))
            ->fillForm(function (array $arguments): array {
                $reserva = $this->reservaDelRestaurante($arguments);

                if ($reserva === null) {
                    return ['fecha' => '', 'hora' => ''];
                }

                $this->recordarValoresVistosAlMover($reserva);

                return [
                    'fecha' => substr((string) $reserva->getRawOriginal('fecha'), 0, 10),
                    'hora' => substr((string) $reserva->getRawOriginal('hora'), 0, 5),
                ];
            })
            ->schema([
                TextInput::make('fecha')->label(__('panel.reservas.campos.fecha'))->type('date'),
                TextInput::make('hora')->label(__('panel.reservas.campos.hora'))->type('time'),
            ])
            ->action(function (array $arguments, array $data, Action $action): void {
                $this->ejecutarEnVentana(function () use ($arguments, $data): void {
                    $reserva = $this->reservaPorId($arguments);

                    app(GestionDeReservas::class)->mover(
                        $this->usuario(),
                        $this->restaurante(),
                        $reserva,
                        $data['hora'] ?? null,
                        $data['fecha'] ?? null,
                        ['hora' => (string) ($arguments['hora_vista'] ?? ''), 'fecha' => (string) ($arguments['fecha_vista'] ?? '')],
                        'admin',
                    );

                    Notification::make()->title(__('panel.reservas.mover.listo'))->success()->send();
                }, alConflicto: function () use ($arguments, $action): void {
                    $this->avisarConflictoAlMover($arguments);
                    $action->halt();
                });
            });
    }

    public function historialAction(): Action
    {
        return Action::make('historial')
            ->label(__('panel.reservas.acciones.historial'))
            ->modalHeading(__('panel.reservas.historial.titulo'))
            ->modalContent(fn (array $arguments) => view('filament.pages.reservas-historial', [
                'eventos' => $this->eventosDe($arguments),
                'zona' => $this->restaurante()->zona_horaria,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('panel.reservas.historial.cerrar'))
            ->action(fn () => null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        $restaurante = $this->restaurante();
        $servicio = app(ReservasDelDia::class);
        $hoy = $servicio->hoy($restaurante);
        $vista = $this->vistaActual();
        $fecha = $this->fechaElegida();

        /** @var Collection<int, Reserva> $reservas */
        $reservas = match ($vista) {
            'hoy' => $servicio->delDia($restaurante, $hoy),
            'fecha' => $fecha === null ? new Collection : $servicio->delDia($restaurante, $fecha),
            default => $servicio->proximas($restaurante, $hoy),
        };

        $pendientes = $reservas->where('estado', 'pendiente')->count();

        if ($this->esSondeo && $pendientes > $this->pendientesVistas) {
            $this->anuncio = trans_choice('panel.reservas.anuncio', $pendientes - $this->pendientesVistas, ['n' => $pendientes - $this->pendientesVistas]);
        } else {
            $this->anuncio = '';
        }
        $this->pendientesVistas = $pendientes;

        $ultimos = $this->ultimosEventos($reservas);
        $zona = $restaurante->zona_horaria;

        $filas = $reservas->map(function (Reserva $reserva) use ($ultimos, $zona): array {
            $evento = $ultimos->get($reserva->getKey());

            return [
                'reserva' => $reserva,
                'hora' => substr((string) $reserva->getRawOriginal('hora'), 0, 5),
                'telefono' => $reserva->telefono ? preg_replace('/[^0-9+]/', '', $reserva->telefono) : null,
                'rastro' => $evento === null ? null : __('panel.reservas.historial.ultimo', [
                    'accion' => __("panel.reservas.historial.acciones.{$evento->accion}"),
                    'quien' => $evento->usuario?->name ?? __('panel.reservas.historial.sistema'),
                    'cuando' => FormatoFecha::hora($evento->created_at->copy()->setTimezone($zona)),
                ]),
            ];
        });

        $grupos = $filas->groupBy(fn (array $fila): string => $fila['reserva']->fecha->toDateString());

        return [
            'vista' => $vista,
            'fecha' => $fecha,
            'hoy' => $hoy,
            'grupos' => $grupos,
            'etiquetaDia' => fn (string $dia): string => $dia === $hoy
                ? __('panel.reservas.dia.hoy', ['fecha' => self::etiquetaDelDia($dia, false)])
                : self::etiquetaDelDia($dia, true),
            'activas' => $reservas->where('estado', '!=', 'cancelada')->count(),
            'donde' => match ($vista) {
                'hoy' => __('panel.reservas.resumen.hoy'),
                'fecha' => $fecha === null ? __('panel.reservas.resumen.sin_fecha') : __('panel.reservas.resumen.fecha', ['fecha' => self::etiquetaDelDia($fecha, false)]),
                default => __('panel.reservas.resumen.proximas'),
            },
            'eligeDia' => $vista === 'fecha' && $fecha === null,
        ];
    }

    private function vistaActual(): string
    {
        return in_array($this->vista, self::VISTAS, true) ? $this->vista : 'proximas';
    }

    /**
     * La fecha elegida, solo si es un "Y-m-d" real.
     */
    private function fechaElegida(): ?string
    {
        if ($this->fecha === null || $this->fecha === '') {
            return null;
        }

        try {
            $fecha = Carbon::createFromFormat('!Y-m-d', $this->fecha);
        } catch (Throwable) {
            return null;
        }

        return $fecha !== false && $fecha->format('Y-m-d') === $this->fecha ? $this->fecha : null;
    }

    private function usuario(): User
    {
        $usuario = auth()->user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }

    private function restaurante(): Restaurante
    {
        $restaurante = Filament::getTenant();
        abort_unless($restaurante instanceof Restaurante, 404);

        return $restaurante;
    }

    /**
     * Confirmar, "Llegó" y Restaurar: sin ventana; la reserva debe seguir en el estado que la pantalla mostraba.
     */
    private function accionDeEstado(string $nombre, string $metodo): Action
    {
        return Action::make($nombre)
            ->label(__("panel.reservas.acciones.{$nombre}"))
            ->action(fn (array $arguments) => $this->ejecutarCambioDeEstado($arguments, $metodo, "panel.reservas.{$nombre}.listo"));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function ejecutarCambioDeEstado(array $arguments, string $metodo, string $clavePersonalizada): void
    {
        $this->ejecutar(function () use ($arguments, $metodo, $clavePersonalizada): void {
            $reserva = $this->reservaPorId($arguments);

            // El estado esperado sale de la lectura de este momento: el servidor no recibe nada del navegador salvo el id.
            app(GestionDeReservas::class)->{$metodo}($this->usuario(), $this->restaurante(), $reserva, (string) $reserva->getRawOriginal('estado'), 'admin');

            Notification::make()->title(__($clavePersonalizada))->success()->send();
        });
    }

    /**
     * Ejecuta una operación sobre una reserva existente; un conflicto o una reserva que ya no existe se avisan sin perder la lista.
     */
    private function ejecutar(Closure $operacion): void
    {
        try {
            $operacion();
        } catch (ConflictoDeReserva) {
            Notification::make()
                ->title(__('panel.reservas.conflicto.titulo'))
                ->body(__('panel.reservas.conflicto.cuerpo'))
                ->warning()
                ->send();
        } catch (ModelNotFoundException) {
            $this->avisarQueNoExiste();
        }
    }

    /**
     * Igual que ejecutar(), para acciones con formulario: los errores de validación del servicio se muestran en los campos
     * de la ventana abierta, y un conflicto puede tratarse aparte (`$alConflicto`) para dejar la ventana abierta.
     */
    private function ejecutarEnVentana(Closure $operacion, ?Closure $alConflicto = null): void
    {
        try {
            $this->ejecutar($alConflicto === null ? $operacion : function () use ($operacion, $alConflicto): void {
                try {
                    $operacion();
                } catch (ConflictoDeReserva) {
                    $alConflicto();
                }
            });
        } catch (ValidationException $e) {
            $ventana = array_key_last($this->mountedActions);

            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn (array $mensajes, string $campo): array => ["mountedActions.{$ventana}.data.{$campo}" => $mensajes])->all()
            );
        }
    }

    private function avisarQueNoExiste(): void
    {
        Notification::make()
            ->title(__('panel.reservas.no_existe.titulo'))
            ->body(__('panel.reservas.no_existe.cuerpo'))
            ->warning()
            ->send();
    }

    /**
     * Otra persona movió la reserva mientras se editaba: la ventana sigue abierta con lo que se eligió y los valores esperados
     * pasan a ser los actuales, así que el siguiente Guardar es una decisión consciente.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function avisarConflictoAlMover(array $arguments): void
    {
        $actual = $this->reservaDelRestaurante($arguments);

        if ($actual === null) {
            $this->avisarQueNoExiste();

            return;
        }

        $this->recordarValoresVistosAlMover($actual);

        Notification::make()
            ->title(__('panel.reservas.conflicto.titulo'))
            ->body(__('panel.reservas.conflicto.mover', [
                'estado' => mb_strtolower(__("panel.reservas.estados.{$actual->estado}")),
                'cuando' => $this->cuando($actual),
            ]))
            ->warning()
            ->send();
    }

    /**
     * La reserva que pide el navegador, siempre dentro del restaurante actual: una de otro restaurante, inexistente o con un
     * id que no es número responde como "no existe".
     *
     * @param  array<string, mixed>  $arguments
     */
    private function reservaPorId(array $arguments): Reserva
    {
        $id = $arguments['reserva'] ?? null;

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            throw (new ModelNotFoundException)->setModel(Reserva::class);
        }

        return Reserva::withoutGlobalScopes()
            ->where('restaurante_id', $this->restaurante()->getKey())
            ->find((int) $id)
            ?? throw (new ModelNotFoundException)->setModel(Reserva::class, [(int) $id]);
    }

    /**
     * Solo para mostrar texto: la reserva o null si no existe en este restaurante.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function reservaDelRestaurante(array $arguments): ?Reserva
    {
        try {
            return $this->reservaPorId($arguments);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * Hora y fecha que la persona vio al abrir "Mover", guardadas por el servidor en la ventana abierta (sobrescriben lo que
     * el navegador haya mandado) para detectar que otra persona la movió mientras se editaba.
     */
    private function recordarValoresVistosAlMover(Reserva $reserva): void
    {
        $ventana = array_key_last($this->mountedActions);

        if ($ventana === null) {
            return;
        }

        $this->mountedActions[$ventana]['arguments']['hora_vista'] = (string) $reserva->getRawOriginal('hora');
        $this->mountedActions[$ventana]['arguments']['fecha_vista'] = (string) $reserva->getRawOriginal('fecha');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return Collection<int, ReservaEvento>
     */
    private function eventosDe(array $arguments): Collection
    {
        abort_unless(static::canAccess(), 403);

        $id = $arguments['reserva'] ?? null;

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return new Collection;
        }

        return ReservaEvento::query()
            ->where('restaurante_id', $this->restaurante()->getKey())
            ->where('reserva_id', (int) $id)
            ->with('usuario:id,name')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    /**
     * El último evento de cada reserva de la lista, en una sola consulta.
     *
     * @param  Collection<int, Reserva>  $reservas
     * @return \Illuminate\Support\Collection<int, ReservaEvento>
     */
    private function ultimosEventos(Collection $reservas): \Illuminate\Support\Collection
    {
        if ($reservas->isEmpty()) {
            return collect();
        }

        return ReservaEvento::query()
            ->where('restaurante_id', $this->restaurante()->getKey())
            ->whereIn('reserva_id', $reservas->modelKeys())
            ->with('usuario:id,name')
            ->orderByDesc('id')
            ->get()
            ->unique('reserva_id')
            ->keyBy('reserva_id');
    }

    /**
     * "hoy a las 20:00" o "el lun 27 oct a las 20:00".
     */
    private function cuando(Reserva $reserva): string
    {
        $hora = substr((string) $reserva->getRawOriginal('hora'), 0, 5);
        $dia = $reserva->fecha->toDateString();

        return $dia === app(ReservasDelDia::class)->hoy($this->restaurante())
            ? __('panel.reservas.cuando.hoy', ['hora' => $hora])
            : __('panel.reservas.cuando.otro', ['fecha' => self::etiquetaDelDia($dia, false), 'hora' => $hora]);
    }

    /**
     * "lun 27 oct" o, en largo, "Lunes 27 de octubre". Usa los nombres de Carbon, sin depender de intl.
     */
    private static function etiquetaDelDia(string $dia, bool $largo): string
    {
        $fecha = Carbon::createFromFormat('!Y-m-d', $dia)->locale('es');

        if (! $largo) {
            return $fecha->isoFormat('ddd D MMM');
        }

        $texto = $fecha->isoFormat('dddd D [de] MMMM');

        return mb_strtoupper(mb_substr($texto, 0, 1)).mb_substr($texto, 1);
    }
}
