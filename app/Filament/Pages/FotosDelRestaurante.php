<?php

namespace App\Filament\Pages;

use App\Filament\Components\ImagenRestauranteUpload;
use App\Models\GaleriaImagen;
use App\Models\Restaurante;
use App\Services\AparienciaSitio;
use App\Services\WebpImageService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Fotos de tu restaurante: la galería del sitio. Subir varias a la vez, ordenarlas arrastrando, escribir una
 * descripción opcional y quitarlas. Cada subida pasa por WebpImageService (perfil "galeria"), igual que /panel.
 * Lo ve y lo cambia quien indica GaleriaImagenPolicy; las fotos de otro restaurante nunca aparecen
 * (RestauranteScope) y no se pueden mover ni borrar.
 */
class FotosDelRestaurante extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'fotos';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    /**
     * Resultado de la última subida: fotos guardadas y mensajes de las que no se pudieron guardar.
     *
     * @var array{guardadas: int, errores: list<string>}
     */
    public array $resultadoDeSubida = ['guardadas' => 0, 'errores' => []];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('panel.grupos.apariencia');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.fotos.nav');
    }

    public static function canAccess(): bool
    {
        return Filament::getTenant() instanceof Restaurante && (auth()->user()?->can('viewAny', GaleriaImagen::class) ?? false);
    }

    public function hydrate(): void
    {
        abort_unless(static::canAccess(), 404);
    }

    public function getTitle(): string
    {
        return __('panel.fotos.titulo');
    }

    public function getSubheading(): ?string
    {
        return __('panel.fotos.subtitulo');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('panel.fotos.sin_galeria.titulo'))
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->iconColor('warning')
                ->visible(fn (): bool => ! AparienciaSitio::muestraGaleria(Filament::getTenant()?->sitio_plantilla))
                ->schema([
                    Text::make(fn (): string => __('panel.fotos.sin_galeria.descripcion', [
                        'actual' => AparienciaSitio::nombre((string) Filament::getTenant()?->sitio_plantilla),
                        'disenos' => Arr::join(AparienciaSitio::nombresConGaleria(), ', ', ' y '),
                    ])),
                    Actions::make([
                        Action::make('ir_a_apariencia')
                            ->label(__('panel.fotos.sin_galeria.accion'))
                            ->url(fn (): string => Apariencia::getUrl())
                            ->visible(fn (): bool => Apariencia::canAccess()),
                    ]),
                ]),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        $imagenes = app(WebpImageService::class);

        return $table
            ->query(fn (): Builder => GaleriaImagen::query())
            ->columns([
                ImageColumn::make('miniatura')
                    ->label(__('panel.fotos.foto'))
                    ->state(fn (GaleriaImagen $record): ?string => $imagenes->url($record->imagen, 480))
                    ->imageHeight(96)
                    ->extraImgAttributes(['alt' => '', 'class' => 'rounded-lg object-cover']),
                TextInputColumn::make('descripcion')
                    ->label(__('panel.fotos.descripcion'))
                    ->placeholder(__('panel.fotos.descripcion_ejemplo'))
                    ->rules(['nullable', 'string', 'max:120'])
                    ->disabled(fn (): bool => ! $this->puedeCambiar())
                    ->updateStateUsing(function (GaleriaImagen $record, ?string $state): ?string {
                        Gate::authorize('update', $record);

                        $record->update(['descripcion' => filled($state) ? trim($state) : null]);

                        return $record->descripcion;
                    }),
            ])
            ->stackedOnMobile()
            ->defaultSort('orden')
            ->reorderable('orden', fn (): bool => $this->puedeCambiar())
            ->reorderRecordsTriggerAction(fn (Action $action, bool $isReordering): Action => $action
                ->label($isReordering ? __('panel.fotos.terminar_orden') : __('panel.fotos.ordenar')))
            ->description(fn (): string => $this->textoDeContador())
            ->headerActions([$this->accionSubir()])
            ->recordActions([$this->accionQuitar()])
            ->emptyStateHeading(__('panel.fotos.vacio_titulo'))
            ->emptyStateDescription(__('panel.fotos.vacio_descripcion'))
            ->emptyStateIcon(Heroicon::OutlinedPhoto);
    }

    private function accionSubir(): Action
    {
        return Action::make('subir')
            ->label(__('panel.fotos.subir'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->visible(fn (): bool => Gate::allows('create', GaleriaImagen::class))
            ->disabled(fn (): bool => $this->lugaresLibres() === 0)
            ->tooltip(fn (): ?string => $this->lugaresLibres() === 0 ? __('panel.fotos.lleno') : null)
            ->modalHeading(__('panel.fotos.subir_titulo'))
            ->modalDescription(fn (): string => __('panel.fotos.subir_descripcion', ['n' => $this->lugaresLibres()]))
            ->modalSubmitActionLabel(__('panel.fotos.subir_boton'))
            ->schema(fn (): array => [
                ImagenRestauranteUpload::make('fotos', 'galeria')
                    ->label(__('panel.fotos.campo'))
                    ->multiple()
                    ->maxFiles(max(1, $this->lugaresLibres()))
                    ->required()
                    ->validationMessages(['max_files' => __('panel.fotos.demasiadas', ['n' => $this->lugaresLibres()])])
                    ->saveUploadedFileUsing(fn (FileUpload $component, TemporaryUploadedFile $file): ?string => $this->guardarFoto($file)),
            ])
            ->action(function (): void {
                $resultado = $this->resultadoDeSubida;
                $this->resultadoDeSubida = ['guardadas' => 0, 'errores' => []];

                $this->avisarResultado($resultado['guardadas'], $resultado['errores']);
            });
    }

    private function accionQuitar(): Action
    {
        return Action::make('quitar')
            ->label(__('panel.fotos.quitar'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (GaleriaImagen $record): bool => Gate::allows('delete', $record))
            ->requiresConfirmation()
            ->modalHeading(__('panel.fotos.quitar_titulo'))
            ->modalDescription(__('panel.fotos.quitar_descripcion'))
            ->modalSubmitActionLabel(__('panel.fotos.quitar'))
            ->action(function (GaleriaImagen $record): void {
                Gate::authorize('delete', $record);

                $ruta = $record->imagen;
                $record->delete();
                app(WebpImageService::class)->deleteFromRestaurante($ruta, (int) Filament::getTenant()->getKey());

                Notification::make()->success()->title(__('panel.fotos.quitada'))->send();
            });
    }

    /**
     * Procesa una foto subida y crea su registro. Devuelve null (la foto no se guarda) cuando se rechaza;
     * el motivo queda en $resultadoDeSubida para decírselo a la persona al terminar.
     */
    private function guardarFoto(TemporaryUploadedFile $file): ?string
    {
        Gate::authorize('create', GaleriaImagen::class);

        $nombre = $file->getClientOriginalName();

        if ($this->lugaresLibres() === 0) {
            $this->resultadoDeSubida['errores'][] = $nombre.': '.__('panel.fotos.lleno');

            return null;
        }

        $restauranteId = (int) Filament::getTenant()->getKey();

        try {
            $ruta = app(WebpImageService::class)->procesar($file, $restauranteId, 'galeria');
        } catch (ValidationException $e) {
            $this->resultadoDeSubida['errores'][] = $nombre.': '.collect($e->errors())->flatten()->first();

            return null;
        }

        GaleriaImagen::create([
            'restaurante_id' => $restauranteId,
            'imagen' => $ruta,
            'orden' => (int) GaleriaImagen::max('orden') + 1,
        ]);
        $this->resultadoDeSubida['guardadas']++;

        return null;
    }

    /**
     * @param  list<string>  $errores
     */
    private function avisarResultado(int $guardadas, array $errores): void
    {
        if ($errores === []) {
            Notification::make()->success()->title(trans_choice('panel.fotos.subidas', $guardadas, ['n' => $guardadas]))->send();

            return;
        }

        Notification::make()
            ->title($guardadas > 0
                ? trans_choice('panel.fotos.subidas_parcial', $guardadas, ['n' => $guardadas])
                : __('panel.fotos.ninguna_subida'))
            ->body(implode("\n", $errores))
            ->color($guardadas > 0 ? 'warning' : 'danger')
            ->persistent()
            ->send();
    }

    private function puedeCambiar(): bool
    {
        $restaurante = Filament::getTenant();

        return $restaurante instanceof Restaurante
            && (auth()->user()?->can('update', GaleriaImagen::make(['restaurante_id' => $restaurante->getKey()])) ?? false);
    }

    private function maximo(): int
    {
        return (int) config('menuonl.imagenes.max_galeria');
    }

    private function lugaresLibres(): int
    {
        return max(0, $this->maximo() - GaleriaImagen::count());
    }

    private function textoDeContador(): string
    {
        return __('panel.fotos.contador', ['n' => GaleriaImagen::count(), 'max' => $this->maximo()])
            .' '.__('panel.fotos.ayuda_orden');
    }
}
