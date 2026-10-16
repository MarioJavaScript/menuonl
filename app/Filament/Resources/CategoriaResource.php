<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoriaResource\Pages\CreateCategoria;
use App\Filament\Resources\CategoriaResource\Pages\EditCategoria;
use App\Filament\Resources\CategoriaResource\Pages\ListCategorias;
use App\Models\Categoria;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CategoriaResource extends Resource
{
    protected static ?string $model = Categoria::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la Categoría')
                    ->schema([
                        Toggle::make('visible')
                            ->label('Visible en menú')
                            ->default(true),
                        TextInput::make('orden')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Repeater::make('traducciones')
                            ->relationship('traducciones')
                            ->label('Nombres y Descripciones (Traducciones)')
                            ->schema([
                                Select::make('idioma')
                                    ->label('Idioma')
                                    ->options(function () {
                                        $tenant = Filament::getTenant();
                                        $default = $tenant?->idioma_default ?? 'es';

                                        return [
                                            $default => strtoupper($default).' (Principal)',
                                            'en' => 'EN (Inglés)',
                                            'fr' => 'FR (Francés)',
                                            'pt' => 'PT (Portugués)',
                                            'it' => 'IT (Italiano)',
                                            'de' => 'DE (Alemán)',
                                        ];
                                    })
                                    ->default(fn () => Filament::getTenant()?->idioma_default ?? 'es')
                                    ->required(),
                                TextInput::make('nombre')
                                    ->label('Nombre de Categoría')
                                    ->required()
                                    ->maxLength(120),
                                Textarea::make('descripcion')
                                    ->label('Descripción')
                                    ->rows(2)
                                    ->nullable(),
                            ])
                            ->defaultItems(1)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('orden')
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->state(fn (Categoria $record) => $record->getNombre() ?? "Categoría #{$record->id}")
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('traducciones', fn ($q) => $q->where('nombre', 'like', "%{$search}%"))),
                ToggleColumn::make('visible')
                    ->label('Visible')
                    ->sortable(),
                TextColumn::make('orden')
                    ->label('Orden')
                    ->sortable(),
                TextColumn::make('productos_count')
                    ->counts('productos')
                    ->label('Platillos')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
            'index' => ListCategorias::route('/'),
            'create' => CreateCategoria::route('/create'),
            'edit' => EditCategoria::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
