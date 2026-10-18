<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HistorialCambioResource\Pages\ListHistorialCambios;
use App\Models\HistorialCambio;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class HistorialCambioResource extends Resource
{
    protected static ?string $model = HistorialCambio::class;

    protected static ?string $modelLabel = 'registro de auditoría';

    protected static ?string $pluralModelLabel = 'registros de auditoría';

    protected static ?string $navigationLabel = 'Historial de auditoría';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Administración Global';

    protected static ?int $navigationSort = 90;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha / Hora')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('entidad')
                    ->label('Entidad')
                    ->badge()
                    ->sortable(),
                TextColumn::make('entidad_id')
                    ->label('ID Registro')
                    ->sortable(),
                TextColumn::make('accion')
                    ->label('Acción')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'crear' => 'success',
                        'actualizar' => 'warning',
                        'eliminar' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('usuario.name')
                    ->label('Usuario')
                    ->default('Sistema')
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('verCambios')
                    ->label('Ver cambios')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (HistorialCambio $record) => "Auditoría: {$record->accion} en {$record->entidad} #{$record->entidad_id}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn (HistorialCambio $record): View => view('filament.historial.cambios', [
                        'cambios' => $record->cambios ?? [],
                    ])),
            ])
            ->emptyStateHeading('Sin eventos de auditoría')
            ->emptyStateDescription('Las modificaciones realizadas en el sistema se registrarán aquí de forma automática.')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHistorialCambios::route('/'),
        ];
    }
}
