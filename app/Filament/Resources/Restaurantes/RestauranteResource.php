<?php

namespace App\Filament\Resources\Restaurantes;

use App\Filament\Resources\Restaurantes\Pages\CreateRestaurante;
use App\Filament\Resources\Restaurantes\Pages\EditRestaurante;
use App\Filament\Resources\Restaurantes\Pages\ListRestaurantes;
use App\Filament\Resources\Restaurantes\Schemas\RestauranteForm;
use App\Filament\Resources\Restaurantes\Tables\RestaurantesTable;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Platform-wide restaurant management (RF-30, RF-31). Superadmin only: the Restaurante policy
 * denies `viewAny` and `create` to everyone else, so the resource never shows up for them.
 */
class RestauranteResource extends Resource
{
    protected static ?string $model = Restaurante::class;

    protected static ?string $modelLabel = 'restaurante';

    protected static ?string $pluralModelLabel = 'restaurantes';

    protected static ?string $slug = 'restaurantes';

    protected static bool $isScopedToTenant = false;

    protected static \UnitEnum|string|null $navigationGroup = 'Plataforma';

    protected static ?string $navigationLabel = 'Restaurantes';

    protected static ?int $navigationSort = 80;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    public static function form(Schema $schema): Schema
    {
        return RestauranteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RestaurantesTable::configure($table);
    }

    /**
     * Explicitly lifts the restaurant scope: this is the only resource that may cross restaurants.
     * Access is gated by RestaurantePolicy, not by the query.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope(RestauranteScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRestaurantes::route('/'),
            'create' => CreateRestaurante::route('/create'),
            'edit' => EditRestaurante::route('/{record}/edit'),
        ];
    }
}
