<?php

namespace App\Filament\Resources\Horarios;

use App\Filament\Resources\Horarios\Pages\CreateHorario;
use App\Filament\Resources\Horarios\Pages\EditHorario;
use App\Filament\Resources\Horarios\Pages\ListHorarios;
use App\Filament\Resources\Horarios\Schemas\HorarioForm;
use App\Filament\Resources\Horarios\Tables\HorariosTable;
use App\Models\Horario;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HorarioResource extends Resource
{
    protected static ?string $model = Horario::class;

    protected static ?string $modelLabel = 'horario';

    protected static ?string $pluralModelLabel = 'horarios';

    protected static ?string $slug = 'horarios';

    protected static \UnitEnum|string|null $navigationGroup = 'Operación del Local';

    protected static ?string $navigationLabel = 'Horarios de atención';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public static function form(Schema $schema): Schema
    {
        return HorarioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HorariosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHorarios::route('/'),
            'create' => CreateHorario::route('/create'),
            'edit' => EditHorario::route('/{record}/edit'),
        ];
    }
}
