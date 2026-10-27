<?php

namespace App\Filament\Resources\Invitaciones\Schemas;

use App\Services\Catalogos;
use App\Services\PermisosPanel;
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
                    ->label('Correo electrónico')
                    ->placeholder('colaborador@ejemplo.com')
                    ->helperText('Enviaremos un enlace único para que la persona acepte la invitación y configure su acceso.')
                    ->email()
                    ->required()
                    ->maxLength(190)
                    ->rule(fn (): \Closure => self::noEsMiembro()),
                Select::make('rol')
                    ->label('Rol asignado')
                    ->helperText('Propietario: control total de configuración y equipo.')
                    ->options(fn (): array => self::rolesOfrecidos())
                    ->in(fn (): array => array_keys(self::rolesOfrecidos()))
                    ->default(fn (): ?string => array_key_first(self::rolesOfrecidos()))
                    ->required(),
            ]);
    }

    /**
     * Roles the signed-in user can hand out in this restaurant, with their labels (PermisosPanel::ROLES_OFRECIDOS).
     *
     * @return array<string, string>
     */
    private static function rolesOfrecidos(): array
    {
        $usuario = auth()->user();
        $restaurante = Filament::getTenant();

        if ($usuario === null || $restaurante === null) {
            return [];
        }

        $etiquetas = Catalogos::roles();

        return collect(PermisosPanel::rolesAsignables($usuario, (int) $restaurante->getKey()))
            ->mapWithKeys(fn (string $rol): array => [$rol => $etiquetas[$rol] ?? ucfirst($rol)])
            ->all();
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
