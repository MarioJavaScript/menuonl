<?php

namespace App\Filament\Pages\Tenancy;

use App\Filament\Components\ImagenRestauranteUpload;
use App\Services\Catalogos;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Mi restaurante (RF-19): apariencia de la página pública y ajustes del local. Only propietario and
 * superadmin reach it: canView() asks the Restaurante policy for `update`. Logo and cover go
 * through WebpImageService.
 */
class EditRestauranteProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Mi restaurante';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('ver_menu')
                ->label('Ver menú')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): ?string => Filament::getTenant() ? route('menu.publico', ['slug' => Filament::getTenant()->slug]) : null)
                ->openUrlInNewTab(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $temas = Catalogos::temas();
        $plantillas = Catalogos::plantillasSitio();

        return $schema
            ->components([
                Section::make('Diseño del menú')
                    ->description('Personaliza el estilo visual, colores e imágenes que verán tus clientes al abrir tu menú.')
                    ->schema([
                        Radio::make('tema')
                            ->label('Estilo visual')
                            ->options(Catalogos::nombresDeTemas())
                            ->descriptions(array_map(fn (array $tema): string => $tema['descripcion'], $temas))
                            ->in(array_keys($temas))
                            ->default('calido')
                            ->required(),
                        Radio::make('sitio_plantilla')
                            ->label('Plantilla del sitio')
                            ->helperText('Diseño de tu sitio (/tu-restaurante) y de su menú. La plantilla Cafetería trae sus propios colores y tipografías y no usa el estilo visual de arriba.')
                            ->options(array_map(fn (array $plantilla): string => $plantilla['nombre'], $plantillas))
                            ->descriptions(array_map(fn (array $plantilla): string => $plantilla['descripcion'], $plantillas))
                            ->in(array_keys($plantillas))
                            ->default('a')
                            ->required(),
                        ColorPicker::make('color_acento')
                            ->label('Color principal')
                            ->helperText('Se aplica en botones y acentos del menú. Déjalo vacío para conservar el color original del estilo elegido.')
                            ->regex('/^#[0-9a-fA-F]{6}$/'),
                        ImagenRestauranteUpload::make('portada', 'portadas')
                            ->label('Fotografía de portada')
                            ->helperText('Una foto horizontal de tus platillos o del ambiente de tu local luce mejor. Formatos JPG, PNG o WebP (máx. 5 MB).'),
                        ImagenRestauranteUpload::make('logo', 'logos')
                            ->label('Logotipo')
                            ->helperText('Opcional. Si no subes uno, el menú destacará el nombre de tu restaurante con su tipografía principal.'),
                        TextInput::make('eslogan')
                            ->label('Frase corta o especialidad')
                            ->placeholder('Cocina italiana de leña')
                            ->helperText('Aparece junto al nombre de tu restaurante en el encabezado.')
                            ->maxLength(120),
                        Textarea::make('descripcion')
                            ->label('Descripción breve')
                            ->placeholder('Cuéntales a tus clientes qué hace especial a tu cocina o comparte indicaciones útiles.')
                            ->rows(3)
                            ->maxLength(500),
                    ]),
                Section::make('Mi restaurante')
                    ->description('Información general, moneda e idioma principal de tu carta.')
                    ->schema([
                        TextInput::make('nombre')->label('Nombre del restaurante')->required()->maxLength(150),
                        Select::make('moneda')->label('Moneda')->options(Catalogos::monedas())->required(),
                        Select::make('idioma_default')->label('Idioma principal')->options(Catalogos::idiomas())->required(),
                        Select::make('zona_horaria')->label('Zona horaria')->options(Catalogos::zonasHorarias())->searchable()->required(),
                    ])
                    ->columns(2),
                Section::make('Pedidos por WhatsApp')
                    ->description('Permite que tus clientes armen su pedido en el menú y lo envíen listo a tu WhatsApp.')
                    ->schema([
                        Toggle::make('pedidos_activos')
                            ->label('Activar pedidos por WhatsApp')
                            ->helperText('Habilita la selección de platillos en el menú público para enviar el pedido por WhatsApp.'),
                        TextInput::make('whatsapp_pedidos')
                            ->label('WhatsApp para pedidos')
                            ->placeholder('5215512345678')
                            ->helperText('Número en formato internacional con código de país, solo dígitos (8 a 20 dígitos, sin espacios ni signos).')
                            ->regex('/^[0-9]{8,20}$/')
                            ->maxLength(20),
                        Toggle::make('mostrar_agotados')
                            ->label('Mostrar platillos agotados')
                            ->helperText('Si se desmarca, los platillos no disponibles se ocultan por completo del menú público.'),
                    ]),
                Section::make('Idiomas adicionales')
                    ->description('Agrega otros idiomas si recibes clientes extranjeros y deseas traducir tus categorías y platillos.')
                    ->collapsed()
                    ->schema([
                        Repeater::make('idiomas')
                            ->label('Idiomas activos')
                            ->relationship('idiomas')
                            ->schema([
                                Select::make('idioma')->label('Idioma')->options(Catalogos::idiomas())->required()->distinct(),
                                TextInput::make('orden')->label('Orden')->numeric()->integer()->minValue(0)->maxValue(32767)->default(0)->required(),
                            ])
                            ->columns(2)
                            ->orderColumn('orden')
                            ->addActionLabel('Agregar idioma'),
                    ]),
            ]);
    }
}
