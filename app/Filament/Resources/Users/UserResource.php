<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Users of the active restaurant (RF-13) and, for the superadmin, of the whole platform (RF-31).
 * Accounts are created through invitations; this resource only lists them and revokes access.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $slug = 'usuarios';

    protected static bool $isScopedToTenant = false;

    protected static ?int $navigationSort = 70;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /**
     * Users are not owned by a restaurant, so the tenant scope does not apply: members are
     * filtered by hand, and only the superadmin sees every account.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()?->isSuperadmin()) {
            return $query;
        }

        $tenant = Filament::getTenant();

        return $query->whereHas('restaurantes', fn (Builder $restaurantes): Builder => $restaurantes->whereKey($tenant?->getKey() ?? 0));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
