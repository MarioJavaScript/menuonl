<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CodigoQrResource\Pages\CreateCodigoQr;
use App\Filament\Resources\CodigoQrResource\Pages\EditCodigoQr;
use App\Filament\Resources\CodigoQrResource\Pages\ListCodigoQrs;
use App\Models\CodigoQr;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CodigoQrResource extends Resource
{
    protected static ?string $model = CodigoQr::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static UnitEnum|string|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuración del Código QR')
                    ->columns(2)
                    ->schema([
                        Select::make('tipo')
                            ->label('Tipo de Código')
                            ->options([
                                'general' => 'General (Menú completo)',
                                'mesa' => 'Por Mesa (Asignado a comensal)',
                            ])
                            ->default('mesa')
                            ->required()
                            ->live(),
                        TextInput::make('mesa')
                            ->label('Número / Identificador de Mesa')
                            ->placeholder('ej. 1, 14, Terraza-2')
                            ->maxLength(20)
                            ->required(fn ($get) => $get('tipo') === 'mesa')
                            ->visible(fn ($get) => $get('tipo') === 'mesa'),
                        TextInput::make('nombre')
                            ->label('Nombre descriptivo')
                            ->placeholder('ej. General Barra o Mesa 5')
                            ->maxLength(80)
                            ->nullable(),
                        TextInput::make('codigo')
                            ->label('Código Corto Único (16 caracteres máx.)')
                            ->default(fn () => strtolower(Str::random(16)))
                            ->maxLength(16)
                            ->unique(ignoreRecord: true)
                            ->required(),
                        Toggle::make('activo')
                            ->label('Código Activo')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->copyable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                TextColumn::make('mesa')
                    ->label('Mesa')
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('nombre')
                    ->label('Identificador')
                    ->searchable(),
                ToggleColumn::make('activo')
                    ->label('Activo')
                    ->sortable(),
                TextColumn::make('contador_escaneos')
                    ->label('Escaneos')
                    ->sortable(),
                TextColumn::make('ultimo_escaneo_en')
                    ->label('Último Escaneo')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
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
            'index' => ListCodigoQrs::route('/'),
            'create' => CreateCodigoQr::route('/create'),
            'edit' => EditCodigoQr::route('/{record}/edit'),
        ];
    }
}
