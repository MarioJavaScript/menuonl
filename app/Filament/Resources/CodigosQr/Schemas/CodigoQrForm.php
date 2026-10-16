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
                TextInput::make('nombre')
                    ->label('Nombre identificador')
                    ->placeholder('Ej. Mesa 1, Barra, Terraza')
                    ->required()
                    ->maxLength(80)
                    ->helperText('Nombre descriptivo para identificar este código QR en el panel.'),
                TextInput::make('mesa')
                    ->label('Número o identificador de mesa')
                    ->placeholder('1')
                    ->maxLength(20)
                    ->regex('/\A[A-Za-z0-9 _-]{1,20}\z/')
                    ->helperText('Opcional. Si se define, el menú público asociará automáticamente los pedidos a esta mesa.'),
                TextInput::make('codigo')
                    ->label('Código corto')
                    ->placeholder('m1-barra')
                    ->helperText('Identificador único para la URL corta (/q/{código}). Se genera automáticamente si se deja vacío.')
                    ->required()
                    ->maxLength(16)
                    ->rule(new CodigoQrValido)
                    ->unique(ignoreRecord: true)
                    ->default(fn (): string => Str::lower(Str::random(8))),
                Toggle::make('activo')
                    ->label('Código QR activo')
                    ->helperText('Si se desactiva, el escaneo mostrará un aviso de código no disponible.')
                    ->default(true),
            ]);
    }
}
