<?php

namespace App\Filament\Resources\Contactos\Schemas;

use App\Rules\ValorContactoSeguro;
use App\Services\Catalogos;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ContactoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tipo')->label('Tipo')->options(Catalogos::tiposContacto())->required()->live(),
                TextInput::make('etiqueta')->label('Etiqueta')->maxLength(80),
                TextInput::make('valor')
                    ->label('Valor')
                    ->required()
                    ->maxLength(255)
                    ->rule(fn (Get $get): ValorContactoSeguro => new ValorContactoSeguro($get('tipo'))),
                TextInput::make('orden')->label('Orden')->numeric()->integer()->minValue(0)->maxValue(1000000)->default(0)->required(),
                Toggle::make('visible')->label('Visible en el menú')->default(true),
            ]);
    }
}
