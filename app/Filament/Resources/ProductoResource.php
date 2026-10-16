<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductoResource\Pages\CreateProducto;
use App\Filament\Resources\ProductoResource\Pages\EditProducto;
use App\Filament\Resources\ProductoResource\Pages\ListProductos;
use App\Models\Categoria;
use App\Models\Producto;
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
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static UnitEnum|string|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Clasificación e Imagen')
                    ->columns(2)
                    ->schema([
                        Select::make('categoria_id')
                            ->label('Categoría')
                            ->relationship(
                                name: 'categoria',
                                modifyQueryUsing: fn (Builder $query) => $query->with('traducciones'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Categoria $c) => $c->getNombre() ?? "Categoría #{$c->id}")
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('etiquetas')
                            ->label('Etiquetas Dietéticas')
                            ->multiple()
                            ->relationship('etiquetas', 'codigo')
                            ->preload(),
                        FileUpload::make('imagen')
                            ->label('Foto del Platillo')
                            ->image()
                            ->disk('public')
                            ->directory('productos')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),

                Section::make('Nombres y Descripciones (Traducciones)')
                    ->schema([
                        Repeater::make('traducciones')
                            ->relationship('traducciones')
                            ->label('Traducciones por Idioma')
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
                                    ->label('Nombre del platillo')
                                    ->required()
                                    ->maxLength(150),
                                Textarea::make('descripcion')
                                    ->label('Descripción')
                                    ->rows(2)
                                    ->nullable(),
                            ])
                            ->defaultItems(1)
                            ->columnSpanFull(),
                    ]),

                Section::make('Precios y Disponibilidad')
                    ->columns(3)
                    ->schema([
                        TextInput::make('precio')
                            ->label('Precio Base ($)')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0)
                            ->required(),
                        TextInput::make('precio_oferta')
                            ->label('Precio Oferta ($)')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0)
                            ->nullable(),
                        TextInput::make('orden')
                            ->label('Orden')
                            ->numeric()
                            ->default(0),
                        Toggle::make('disponible')
                            ->label('Disponible')
                            ->default(true),
                        Toggle::make('visible')
                            ->label('Visible en menú')
                            ->default(true),
                        Toggle::make('destacado')
                            ->label('Destacado (Recomendados)')
                            ->default(false),
                    ]),

                Section::make('Variantes de Platillo (Tamaños, Opciones)')
                    ->schema([
                        Repeater::make('variantes')
                            ->relationship('variantes')
                            ->label('Variantes')
                            ->schema([
                                TextInput::make('precio')
                                    ->label('Precio de Variante ($)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->minValue(0)
                                    ->required(),
                                Toggle::make('disponible')
                                    ->label('Disponible')
                                    ->default(true),
                                TextInput::make('orden')
                                    ->label('Orden')
                                    ->numeric()
                                    ->default(0),
                                Repeater::make('traducciones')
                                    ->relationship('traducciones')
                                    ->label('Nombres / Idiomas')
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
                                            ->label('Nombre de variante')
                                            ->placeholder('ej. Chico, Mediano, Grande')
                                            ->required()
                                            ->maxLength(80),
                                    ])
                                    ->defaultItems(1)
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
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
                ImageColumn::make('imagen')
                    ->circular()
                    ->label('Foto'),
                TextColumn::make('nombre')
                    ->label('Platillo')
                    ->state(fn (Producto $record) => $record->getNombre() ?? "Platillo #{$record->id}")
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('traducciones', fn ($q) => $q->where('nombre', 'like', "%{$search}%"))),
                TextColumn::make('categoria')
                    ->label('Categoría')
                    ->state(fn (Producto $record) => $record->categoria?->getNombre() ?? '-'),
                TextInputColumn::make('precio')
                    ->label('Precio ($)')
                    ->rules(['numeric', 'min:0'])
                    ->sortable(),
                TextInputColumn::make('precio_oferta')
                    ->label('Oferta ($)')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->sortable(),
                ToggleColumn::make('disponible')
                    ->label('Disponible')
                    ->sortable(),
                ToggleColumn::make('visible')
                    ->label('Visible')
                    ->sortable(),
                ToggleColumn::make('destacado')
                    ->label('Destacado')
                    ->sortable(),
                TextColumn::make('orden')
                    ->label('Orden')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('categoria_id')
                    ->label('Categoría')
                    ->relationship('categoria', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Categoria $c) => $c->getNombre() ?? "Categoría #{$c->id}"),
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
            'index' => ListProductos::route('/'),
            'create' => CreateProducto::route('/create'),
            'edit' => EditProducto::route('/{record}/edit'),
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
