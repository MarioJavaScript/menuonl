<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HorarioResource\Pages\CreateHorario;
use App\Filament\Resources\HorarioResource\Pages\EditHorario;
use App\Filament\Resources\HorarioResource\Pages\ListHorarios;
use App\Models\Horario;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class HorarioResource extends Resource
{
    protected static ?string $model = Horario::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static UnitEnum|string|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Definición de Horario y Turno')
                    ->columns(2)
                    ->schema([
                        Select::make('dia_semana')
                            ->label('Día de la Semana')
                            ->options([
                                0 => 'Domingo',
                                1 => 'Lunes',
                                2 => 'Martes',
                                3 => 'Miércoles',
                                4 => 'Jueves',
                                5 => 'Viernes',
                                6 => 'Sábado',
                            ])
                            ->required(),
                        TextInput::make('turno')
                            ->label('Turno')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),
                        Toggle::make('cerrado')
                            ->label('Cerrado todo el día')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),
                        TimePicker::make('abre')
                            ->label('Hora de Apertura')
                            ->seconds(false)
                            ->required(fn ($get) => ! (bool) $get('cerrado')),
                        TimePicker::make('cierra')
                            ->label('Hora de Cierre')
                            ->seconds(false)
                            ->helperText('Permite turnos nocturnos donde la hora de cierre es menor que la de apertura (ej. 20:00 a 02:00).')
                            ->required(fn ($get) => ! (bool) $get('cerrado')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('dia_semana')
            ->columns([
                TextColumn::make('dia_semana')
                    ->label('Día')
                    ->formatStateUsing(fn ($state) => match ((int) $state) {
                        0 => 'Domingo',
                        1 => 'Lunes',
                        2 => 'Martes',
                        3 => 'Miércoles',
                        4 => 'Jueves',
                        5 => 'Viernes',
                        6 => 'Sábado',
                        default => (string) $state,
                    })
                    ->sortable(),
                TextColumn::make('turno')
                    ->label('Turno')
                    ->sortable(),
                TextColumn::make('abre')
                    ->label('Apertura')
                    ->placeholder('-'),
                TextColumn::make('cierra')
                    ->label('Cierre')
                    ->placeholder('-'),
                IconColumn::make('cerrado')
                    ->label('Cerrado')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
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
