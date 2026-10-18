<?php

namespace App\Filament\Pages\Tenancy;

use App\Filament\Components\ImagenRestauranteUpload;
use App\Services\Catalogos;
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

    public function form(Schema $schema): Schema
    {
        $temas = Catalogos::temas();

        return $schema
            ->components([
                Section::make('Así se ve tu página')
                    ->description('Elige el estilo que más se parezca a tu local. Se aplica de inmediato en tu menú público.')
                    ->schema([
                        Radio::make('tema')
                            ->label('Estilo')
                            ->options(Catalogos::nombresDeTemas())
                            ->descriptions(array_map(fn (array $tema): string => $tema['descripcion'], $temas))
                            ->in(array_keys($temas))
                            ->default('calido')
                            ->required(),
                        ColorPicker::make('color_acento')
                            ->label('Color principal (opcional)')
                            ->helperText('Para botones y detalles. Elige un color oscuro o intenso para que el texto se lea bien. Déjalo vacío para usar el color del estilo.')
                            ->regex('/^#[0-9a-fA-F]{6}$/'),
                        TextInput::make('eslogan')
                            ->label('Frase corta')
                            ->placeholder('Cocina italiana de leña')
                            ->helperText('Aparece sobre el nombre de tu restaurante.')
                            ->maxLength(120),
                        Textarea::make('descripcion')
                            ->label('Descripción')
                            ->rows(3)
                            ->maxLength(500),
                        ImagenRestauranteUpload::make('logo', 'logos')->label('Logo'),
                        ImagenRestauranteUpload::make('portada', 'portadas')
                            ->label('Foto de portada')
                            ->helperText('Una foto horizontal de tu comida o tu local se ve mejor. Formatos permitidos: JPG, PNG o WebP (máx. 5 MB); se optimiza automáticamente.'),
                    ]),
                Section::make('Datos del local')
                    ->schema([
                        TextInput::make('nombre')->label('Nombre')->required()->maxLength(150),
                        Select::make('moneda')->label('Moneda')->options(Catalogos::monedas())->required(),
                        Select::make('idioma_default')->label('Idioma principal')->options(Catalogos::idiomas())->required(),
                        Select::make('zona_horaria')->label('Zona horaria')->options(Catalogos::zonasHorarias())->searchable()->required(),
                    ])
                    ->columns(2),
                Section::make('Pedidos por WhatsApp')
                    ->schema([
                        TextInput::make('whatsapp_pedidos')
                            ->label('WhatsApp para pedidos')
                            ->placeholder('5215512345678')
                            ->helperText('Número en formato internacional con código de país, solo dígitos (8 a 20 dígitos, sin espacios ni signos).')
                            ->regex('/^[0-9]{8,20}$/')
                            ->maxLength(20),
                        Toggle::make('pedidos_activos')
                            ->label('Activar pedidos por WhatsApp')
                            ->helperText('Habilita el carrito en el menú público para que los comensales envíen sus pedidos directo a WhatsApp.'),
                        Toggle::make('mostrar_agotados')
                            ->label('Mostrar platillos agotados')
                            ->helperText('Si se desmarca, los platillos no disponibles se ocultan por completo del menú público.'),
                    ]),
                Section::make('Idiomas de tu menú')
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
