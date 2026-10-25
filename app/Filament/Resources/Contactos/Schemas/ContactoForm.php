<?php

namespace App\Filament\Resources\Contactos\Schemas;

use App\Filament\Resources\Contactos\ContactoResource;
use App\Rules\ValorContactoSeguro;
use App\Services\Catalogos;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ContactoForm
{
    /**
     * \return array<string, string>
     */
    private static function tiposDisponibles(): array
    {
        $tipos = ContactoResource::tiposPermitidos();

        return $tipos === null ? Catalogos::tiposContacto() : array_intersect_key(Catalogos::tiposContacto(), array_flip($tipos));
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tipo')
                    ->label('Tipo de canal')
                    ->placeholder('Selecciona un canal de contacto')
                    ->options(fn (): array => self::tiposDisponibles())
                    ->required()
                    ->live(),
                TextInput::make('etiqueta')
                    ->label('Etiqueta descriptiva')
                    ->placeholder('Ej. WhatsApp de Reservas, Sucursal Centro')
                    ->maxLength(80)
                    ->helperText('Opcional. Texto visible para identificar este canal de contacto.'),
                TextInput::make('valor')
                    ->label('Valor de contacto')
                    ->placeholder(fn (Get $get): string => match ($get('tipo')) {
                        'telefono', 'whatsapp' => '+52 55 1234 5678',
                        'email' => 'contacto@turestaurante.com',
                        'instagram' => 'https://instagram.com/turestaurante',
                        'facebook' => 'https://facebook.com/turestaurante',
                        'tiktok' => 'https://tiktok.com/@turestaurante',
                        'web' => 'https://www.turestaurante.com',
                        default => 'Enlace o dato de contacto',
                    })
                    ->helperText(fn (Get $get): string => match ($get('tipo')) {
                        'telefono', 'whatsapp' => 'Ingresa el número en formato internacional con código de país.',
                        'email' => 'Correo electrónico válido de atención a clientes.',
                        'instagram', 'facebook', 'tiktok', 'web' => 'Enlace web completo iniciando con https://.',
                        default => 'Dato o enlace seguro para que tus comensales puedan comunicarse.',
                    })
                    ->required()
                    ->maxLength(255)
                    ->rule(fn (Get $get): ValorContactoSeguro => new ValorContactoSeguro($get('tipo'))),
                TextInput::make('orden')
                    ->label('Orden de visualización')
                    ->placeholder('0')
                    ->numeric()->integer()->minValue(0)->maxValue(1000000)->default(0)->required()
                    ->helperText('Posición numérica para ordenar los canales en el menú público.'),
                Toggle::make('visible')
                    ->label('Visible en el menú')
                    ->helperText('Determina si este canal de contacto se muestra en el menú público.')
                    ->default(true),
            ]);
    }
}
