<?php

namespace App\Filament\Resources\Restaurantes\Schemas;

use App\Filament\Components\ImagenRestauranteUpload;
use App\Services\Catalogos;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RestauranteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')->label('Nombre')->required()->maxLength(150),
                TextInput::make('slug')
                    ->label('Slug (URL pública /m/{slug})')
                    ->required()
                    ->maxLength(80)
                    ->regex('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/')
                    ->unique(ignoreRecord: true),
                Select::make('moneda')->label('Moneda')->options(Catalogos::monedas())->default('MXN')->required(),
                Select::make('idioma_default')->label('Idioma principal')->options(Catalogos::idiomas())->default('es')->required(),
                Select::make('zona_horaria')->label('Zona horaria')->options(Catalogos::zonasHorarias())->searchable()->default('America/Mexico_City')->required(),
                Select::make('estado')
                    ->label('Estado')
                    ->options(['activo' => 'Activo', 'suspendido' => 'Suspendido'])
                    ->in(['activo', 'suspendido'])
                    ->default('activo')
                    ->required(),
                ImagenRestauranteUpload::make('logo', 'logos')->label('Logo')->visibleOn('edit'),
                ImagenRestauranteUpload::make('portada', 'portadas')->label('Portada')->visibleOn('edit'),
                TextInput::make('email_propietario')
                    ->label('Correo del propietario (recibe la invitación)')
                    ->email()
                    ->maxLength(190)
                    ->required()
                    ->dehydrated(false)
                    ->visibleOn('create'),
            ]);
    }
}
