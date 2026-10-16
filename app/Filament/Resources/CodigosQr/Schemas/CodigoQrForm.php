<?php

namespace App\Filament\Resources\CodigosQr\Schemas;

use App\Rules\CodigoQrValido;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CodigoQrForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')->label('Nombre')->required()->maxLength(80),
                TextInput::make('mesa')->label('Mesa')->maxLength(20)->regex('/\A[A-Za-z0-9 _-]{1,20}\z/'),
                TextInput::make('codigo')
                    ->label('Código (URL corta /q/{código})')
                    ->required()
                    ->maxLength(16)
                    ->rule(new CodigoQrValido)
                    ->unique(ignoreRecord: true)
                    ->default(fn (): string => Str::lower(Str::random(8))),
                Toggle::make('activo')->label('Activo')->default(true),
            ]);
    }
}
