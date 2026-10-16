<?php

namespace App\Filament\Resources\Invitaciones\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class InvitacionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Correo')
                    ->email()
                    ->required()
                    ->maxLength(190)
                    ->rule(fn (): \Closure => self::noEsMiembro()),
                Select::make('rol')
                    ->label('Rol')
                    ->options(['editor' => 'Editor', 'propietario' => 'Propietario'])
                    ->in(['editor', 'propietario'])
                    ->default('editor')
                    ->required(),
            ]);
    }

    /**
     * Do not invite someone who already belongs to the active restaurant.
     */
    private static function noEsMiembro(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $restauranteId = Filament::getTenant()?->getKey();

            $esMiembro = DB::table('restaurante_usuario')
                ->join('users', 'users.id', '=', 'restaurante_usuario.user_id')
                ->where('restaurante_usuario.restaurante_id', $restauranteId)
                ->where('users.email', mb_strtolower(trim((string) $value)))
                ->exists();

            if ($esMiembro) {
                $fail('Esta persona ya pertenece al restaurante.');
            }
        };
    }
}
