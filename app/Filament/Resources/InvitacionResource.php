<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvitacionResource\Pages\CreateInvitacion;
use App\Filament\Resources\InvitacionResource\Pages\ListInvitacions;
use App\Models\Invitacion;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class InvitacionResource extends Resource
{
    protected static ?string $model = Invitacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static UnitEnum|string|null $navigationGroup = 'Equipo';

    protected static ?int $navigationSort = 1;

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invitar a un Nuevo Miembro')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->required()
                            ->maxLength(190),
                        Select::make('rol')
                            ->label('Rol en el Restaurante')
                            ->options([
                                'editor' => 'Editor (Gestión de Menú, Precios y QR)',
                                'propietario' => 'Propietario (Acceso Completo y Ajustes)',
                            ])
                            ->default('editor')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),
                TextColumn::make('rol')
                    ->label('Rol')
                    ->badge()
                    ->sortable(),
                TextColumn::make('expira_en')
                    ->label('Expira el')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(function (Invitacion $record): string {
                        if ($record->aceptada_en !== null) {
                            return 'Aceptada';
                        }
                        if ($record->isExpired()) {
                            return 'Expirada';
                        }

                        return 'Pendiente';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Aceptada' => 'success',
                        'Expirada' => 'danger',
                        'Pendiente' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('invitadoPor.name')
                    ->label('Invitado por')
                    ->placeholder('Sistema'),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Revocar'),
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
            'index' => ListInvitacions::route('/'),
            'create' => CreateInvitacion::route('/create'),
        ];
    }
}
