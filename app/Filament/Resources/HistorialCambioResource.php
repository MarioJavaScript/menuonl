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
use Illuminate\Support\HtmlString;
use UnitEnum;

class HistorialCambioResource extends Resource
{
    protected static ?string $model = HistorialCambio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Seguridad & Auditoría';

    protected static ?int $navigationSort = 1;

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
                    ->label('Ver Cambios')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (HistorialCambio $record) => "Auditoría: {$record->accion} en {$record->entidad} #{$record->entidad_id}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(function (HistorialCambio $record): HtmlString {
                        $cambios = $record->cambios;
                        if (empty($cambios)) {
                            return new HtmlString('<p class="text-sm text-gray-500">Sin detalles de cambios registrados.</p>');
                        }

                        $html = '<div class="space-y-3 font-mono text-xs">';
                        foreach ($cambios as $campo => $diff) {
                            $antes = is_array($diff) && array_key_exists('antes', $diff) ? json_encode($diff['antes'], JSON_UNESCAPED_UNICODE) : '-';
                            $despues = is_array($diff) && array_key_exists('despues', $diff) ? json_encode($diff['despues'], JSON_UNESCAPED_UNICODE) : '-';
                            $html .= '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">';
                            $html .= '<div class="font-bold text-sm text-gray-900 dark:text-gray-100 mb-1">'.htmlspecialchars((string) $campo).'</div>';
                            $html .= '<div class="text-red-600 dark:text-red-400"><strong>Antes:</strong> '.htmlspecialchars((string) $antes).'</div>';
                            $html .= '<div class="text-green-600 dark:text-green-400"><strong>Después:</strong> '.htmlspecialchars((string) $despues).'</div>';
                            $html .= '</div>';
                        }
                        $html .= '</div>';

                        return new HtmlString($html);
                    }),
            ])
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
