<?php

namespace App\Filament\Pages;

use App\Models\Restaurante;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class ConfiguracionRestaurante extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static UnitEnum|string|null $navigationGroup = 'Ajustes';

    protected static ?string $navigationLabel = 'Configuración';

    protected static ?string $title = 'Configuración del Restaurante';

    protected string $view = 'filament.pages.configuracion-restaurante';

    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return 'Configuración del Restaurante';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->esPropietario());
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $tenant = Filament::getTenant();
        if ($tenant instanceof Restaurante) {
            $this->form->fill($tenant->attributesToArray());
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->model(Filament::getTenant())
            ->components([
                Section::make('Identidad del Restaurante')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre del Restaurante')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('descripcion')
                            ->label('Descripción')
                            ->maxLength(500)
                            ->nullable(),
                        FileUpload::make('logo')
                            ->label('Logotipo')
                            ->image()
                            ->disk('public')
                            ->directory('logos')
                            ->visibility('public')
                            ->maxSize(2048),
                        FileUpload::make('portada')
                            ->label('Imagen de Portada')
                            ->image()
                            ->disk('public')
                            ->directory('portadas')
                            ->visibility('public')
                            ->maxSize(5120),
                    ]),

                Section::make('Preferencias Regionales y Operativas')
                    ->columns(3)
                    ->schema([
                        Select::make('moneda')
                            ->label('Moneda')
                            ->options([
                                'EUR' => 'EUR (€) - Euro',
                                'MXN' => 'MXN ($) - Peso Mexicano',
                                'USD' => 'USD ($) - Dólar Estadounidense',
                                'COP' => 'COP ($) - Peso Colombiano',
                                'ARS' => 'ARS ($) - Peso Argentino',
                                'CLP' => 'CLP ($) - Peso Chileno',
                                'PEN' => 'PEN (S/) - Sol Peruano',
                            ])
                            ->default('EUR')
                            ->required(),
                        Select::make('idioma_default')
                            ->label('Idioma Principal')
                            ->options([
                                'es' => 'Español (es)',
                                'en' => 'English (en)',
                                'fr' => 'Français (fr)',
                                'pt' => 'Português (pt)',
                                'it' => 'Italiano (it)',
                                'de' => 'Deutsch (de)',
                            ])
                            ->default('es')
                            ->required(),
                        Select::make('zona_horaria')
                            ->label('Zona Horaria')
                            ->options([
                                'Europe/Madrid' => 'Europe/Madrid (CET)',
                                'America/Mexico_City' => 'America/Mexico_City (CST)',
                                'America/Bogota' => 'America/Bogota (COT)',
                                'America/Buenos_Aires' => 'America/Buenos_Aires (ART)',
                                'America/Santiago' => 'America/Santiago (CLT)',
                                'America/Lima' => 'America/Lima (PET)',
                                'America/New_York' => 'America/New_York (EST)',
                            ])
                            ->default('Europe/Madrid')
                            ->required(),
                        TextInput::make('whatsapp_pedidos')
                            ->label('WhatsApp de Pedidos')
                            ->placeholder('ej. 34600112233 (solo dígitos)')
                            ->helperText('Solo dígitos numéricos con código de país, sin espacios ni símbolos.')
                            ->rules(['nullable', 'regex:/^[0-9]+$/'])
                            ->maxLength(20)
                            ->nullable(),
                        Toggle::make('pedidos_activos')
                            ->label('Pedidos por WhatsApp Activos')
                            ->helperText('Activa el carrito flotante y el botón de enviar pedido por WhatsApp.')
                            ->default(false),
                        Toggle::make('mostrar_agotados')
                            ->label('Mostrar Platillos Agotados')
                            ->helperText('Si está activo, los platillos agotados se muestran con etiqueta; si no, se ocultan.')
                            ->default(true),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $tenant = Filament::getTenant();

        if ($tenant instanceof Restaurante) {
            $tenant->update($state);

            Notification::make()
                ->title('Configuración guardada')
                ->success()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar Cambios')
                ->submit('save'),
        ];
    }
}
