<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactoResource\Pages\CreateContacto;
use App\Filament\Resources\ContactoResource\Pages\EditContacto;
use App\Filament\Resources\ContactoResource\Pages\ListContactos;
use App\Models\Contacto;
use BackedEnum;
use Closure;
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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class ContactoResource extends Resource
{
    protected static ?string $model = Contacto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static UnitEnum|string|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Canal de Contacto')
                    ->columns(2)
                    ->schema([
                        Select::make('tipo')
                            ->label('Tipo de Contacto')
                            ->options([
                                'whatsapp' => 'WhatsApp',
                                'telefono' => 'Teléfono',
                                'instagram' => 'Instagram',
                                'facebook' => 'Facebook',
                                'email' => 'Correo Electrónico',
                                'web' => 'Sitio Web',
                                'direccion' => 'Dirección Física',
                            ])
                            ->required(),
                        TextInput::make('etiqueta')
                            ->label('Etiqueta (opcional)')
                            ->placeholder('ej. Reservaciones, Pedidos, Barra')
                            ->maxLength(80)
                            ->nullable(),
                        TextInput::make('valor')
                            ->label('Valor / Enlace / Teléfono')
                            ->placeholder('ej. https://instagram.com/mi_restaurante o +34 600 000 000')
                            ->required()
                            ->maxLength(255)
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! Contacto::esUrlPermitida($value)) {
                                        $fail('El valor o URL contiene un protocolo peligroso no permitido (se prohíben javascript: y data:).');
                                    }
                                },
                            ])
                            ->columnSpanFull(),
                        TextInput::make('orden')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Toggle::make('visible')
                            ->label('Visible en menú público')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('orden')
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                TextColumn::make('etiqueta')
                    ->label('Etiqueta')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('valor')
                    ->label('Valor / Enlace')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('orden')
                    ->label('Orden')
                    ->sortable(),
                ToggleColumn::make('visible')
                    ->label('Visible')
                    ->sortable(),
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
            'index' => ListContactos::route('/'),
            'create' => CreateContacto::route('/create'),
            'edit' => EditContacto::route('/{record}/edit'),
        ];
    }
}
