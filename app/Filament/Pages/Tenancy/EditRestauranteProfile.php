<?php

namespace App\Filament\Pages\Tenancy;

use App\Services\Catalogos;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;

/**
 * Restaurant settings (RF-19). Only propietario and superadmin reach it: canView() asks the
 * Restaurante policy for `update`. Logo and cover upload arrive in phase 3.
 */
class EditRestauranteProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Configuración del restaurante';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')->label('Nombre')->required()->maxLength(150),
                TextInput::make('descripcion')->label('Descripción')->maxLength(500),
                Select::make('moneda')->label('Moneda')->options(Catalogos::monedas())->required(),
                Select::make('idioma_default')->label('Idioma principal')->options(Catalogos::idiomas())->required(),
                Select::make('zona_horaria')->label('Zona horaria')->options(Catalogos::zonasHorarias())->searchable()->required(),
                TextInput::make('whatsapp_pedidos')
                    ->label('WhatsApp para pedidos (formato internacional, solo dígitos)')
                    ->regex('/^[0-9]{8,20}$/')
                    ->maxLength(20),
                Toggle::make('pedidos_activos')->label('Activar pedidos por WhatsApp'),
                Toggle::make('mostrar_agotados')->label('Mostrar platillos agotados'),
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
            ]);
    }
}
