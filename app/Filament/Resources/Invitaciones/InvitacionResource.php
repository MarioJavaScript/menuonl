<?php

namespace App\Filament\Resources\Invitaciones;

use App\Filament\Resources\Invitaciones\Pages\CreateInvitacion;
use App\Filament\Resources\Invitaciones\Pages\ListInvitaciones;
use App\Filament\Resources\Invitaciones\Schemas\InvitacionForm;
use App\Filament\Resources\Invitaciones\Tables\InvitacionesTable;
use App\Models\Invitacion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InvitacionResource extends Resource
{
    protected static ?string $model = Invitacion::class;

    protected static ?string $modelLabel = 'invitación';

    protected static ?string $pluralModelLabel = 'invitaciones';

    protected static ?string $slug = 'invitaciones';

    protected static ?string $tenantRelationshipName = 'invitaciones';

    protected static \UnitEnum|string|null $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Invitaciones';

    protected static ?int $navigationSort = 60;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    public static function form(Schema $schema): Schema
    {
        return InvitacionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvitacionesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvitaciones::route('/'),
            'create' => CreateInvitacion::route('/create'),
        ];
    }
}
