<?php

namespace App\Filament\Pages\Tenancy;

use App\Enums\Capacidad;
use App\Models\Restaurante;
use App\Services\Catalogos;
use App\Services\EnlaceGoogleMaps;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Datos del restaurante (RF-19): nombre, frase, descripción, ubicación en el mapa, moneda e idiomas. Only propietario and
 * superadmin reach it: canView() asks the Restaurante policy for `update`. El diseño del sitio vive en Apariencia y los
 * pedidos y reservas en Pedidos y reservas. La dirección corta (slug) no se edita aquí: es de la plataforma.
 */
class EditRestauranteProfile extends EditTenantProfile
{
    private const int MAX_CONSULTAS_POR_MINUTO = 10;

    /**
     * Además del permiso sobre el restaurante, el plan debe incluir la edición de los datos (el superadmin no tiene ese techo).
     */
    public static function canView(Model $tenant): bool
    {
        $usuario = auth()->user();

        return parent::canView($tenant)
            && $tenant instanceof Restaurante
            && $usuario !== null
            && $tenant->permiteA($usuario, Capacidad::EditarDatos);
    }

    public static function getLabel(): string
    {
        return __('panel.datos.nav');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('ver_sitio')
                ->label(__('panel.enlaces.ver_sitio'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->visible(fn (): bool => Filament::getTenant()?->tieneSitio() ?? false)
                ->url(fn (): ?string => Filament::getTenant() ? route('sitio.publico', ['slug' => Filament::getTenant()->slug]) : null)
                ->openUrlInNewTab(),
            Action::make('ver_menu')
                ->label(__('panel.enlaces.ver_menu'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): ?string => Filament::getTenant() ? route('menu.publico', ['slug' => Filament::getTenant()->slug]) : null)
                ->openUrlInNewTab(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.datos.basicos.titulo'))
                    ->description(__('panel.datos.basicos.descripcion'))
                    ->schema([
                        TextInput::make('nombre')
                            ->label(__('panel.datos.basicos.nombre'))
                            ->helperText(__('panel.datos.basicos.nombre_ayuda'))
                            ->required()
                            ->maxLength(150),
                        TextInput::make('eslogan')
                            ->label(__('panel.datos.basicos.frase'))
                            ->placeholder(__('panel.datos.basicos.frase_ejemplo'))
                            ->helperText(__('panel.datos.basicos.frase_ayuda'))
                            ->maxLength(120),
                        Textarea::make('descripcion')
                            ->label(__('panel.datos.basicos.descripcion'))
                            ->placeholder(__('panel.datos.basicos.descripcion_ejemplo'))
                            ->helperText(__('panel.datos.basicos.descripcion_ayuda'))
                            ->rows(3)
                            ->maxLength(500),
                    ]),
                Section::make(__('panel.datos.mapa.titulo'))
                    ->description(__('panel.datos.mapa.descripcion'))
                    ->schema([
                        Text::make(fn (): string => __('panel.datos.mapa.guardado'))
                            ->visible(fn (): bool => $this->tenant->latitud !== null && $this->tenant->longitud !== null),
                        TextInput::make('enlace_mapa')
                            ->label(__('panel.datos.mapa.enlace'))
                            ->placeholder('https://maps.app.goo.gl/...')
                            ->helperText(__('panel.datos.mapa.enlace_ayuda'))
                            ->url()
                            ->maxLength(2048)
                            ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                if (filled($value) && ! EnlaceGoogleMaps::esValido((string) $value)) {
                                    $fail(__('panel.datos.mapa.no_es_google_maps'));
                                }
                            }),
                        Toggle::make('quitar_mapa')
                            ->label(__('panel.datos.mapa.quitar'))
                            ->helperText(__('panel.datos.mapa.quitar_ayuda'))
                            ->visible(fn (): bool => $this->tenant->latitud !== null && $this->tenant->longitud !== null)
                            ->disabled(fn (Get $get): bool => filled($get('enlace_mapa'))),
                    ]),
                Section::make(__('panel.datos.moneda.titulo'))
                    ->description(__('panel.datos.moneda.descripcion'))
                    ->schema([
                        Select::make('moneda')
                            ->label(__('panel.datos.moneda.moneda'))
                            ->helperText(__('panel.datos.moneda.moneda_ayuda'))
                            ->options(Catalogos::monedas())
                            ->required(),
                        Select::make('idioma_default')
                            ->label(__('panel.datos.moneda.idioma'))
                            ->helperText(__('panel.datos.moneda.idioma_ayuda'))
                            ->options(Catalogos::idiomas())
                            ->required(),
                        Select::make('zona_horaria')
                            ->label(__('panel.datos.moneda.zona'))
                            ->helperText(__('panel.datos.moneda.zona_ayuda'))
                            ->options(Catalogos::zonasHorarias())
                            ->searchable()
                            ->required(),
                    ])
                    ->columns(2),
                Section::make(__('panel.datos.idiomas.titulo'))
                    ->description(__('panel.datos.idiomas.descripcion'))
                    ->collapsed()
                    ->schema([
                        Repeater::make('idiomas')
                            ->label(__('panel.datos.idiomas.activos'))
                            ->relationship('idiomas')
                            ->schema([
                                Select::make('idioma')->label(__('panel.datos.idiomas.idioma'))->options(Catalogos::idiomas())->required()->distinct(),
                                TextInput::make('orden')->label(__('panel.datos.idiomas.orden'))->numeric()->integer()->minValue(0)->maxValue(32767)->default(0)->required(),
                            ])
                            ->columns(2)
                            ->orderColumn('orden')
                            ->addActionLabel(__('panel.datos.idiomas.agregar')),
                    ]),
            ]);
    }

    /**
     * El enlace del mapa no es una columna: se convierte en latitud y longitud (o se quita la ubicación guardada).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $enlace = trim((string) ($data['enlace_mapa'] ?? ''));
        $quitar = (bool) ($data['quitar_mapa'] ?? false);
        unset($data['enlace_mapa'], $data['quitar_mapa']);

        if ($enlace !== '') {
            $this->limitarConsultasDeEnlaces($enlace);

            $coordenadas = EnlaceGoogleMaps::coordenadas($enlace);

            if ($coordenadas === null) {
                throw ValidationException::withMessages(['data.enlace_mapa' => __('panel.datos.mapa.sin_ubicacion')]);
            }

            return $data + $coordenadas;
        }

        return $quitar ? $data + ['latitud' => null, 'longitud' => null] : $data;
    }

    /**
     * Seguir un enlace corto hace una petición saliente: se limita por persona (solo el propietario o el superadmin llegan aquí).
     */
    private function limitarConsultasDeEnlaces(string $enlace): void
    {
        if (! EnlaceGoogleMaps::esEnlaceCorto($enlace)) {
            return;
        }

        $clave = 'enlace-mapa:'.auth()->id();

        if (RateLimiter::tooManyAttempts($clave, self::MAX_CONSULTAS_POR_MINUTO)) {
            throw ValidationException::withMessages(['data.enlace_mapa' => __('panel.datos.mapa.demasiados_intentos')]);
        }

        RateLimiter::hit($clave, 60);
    }
}
