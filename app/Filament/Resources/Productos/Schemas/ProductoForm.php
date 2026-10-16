<?php

namespace App\Filament\Resources\Productos\Schemas;

use App\Filament\Components\ImagenRestauranteUpload;
use App\Filament\Components\TraduccionesRepeater;
use App\Models\Categoria;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ProductoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('categoria_id')
                    ->label('Categoría')
                    ->placeholder('Selecciona una categoría')
                    ->options(fn (): array => self::categorias())
                    ->required()
                    ->searchable()
                    ->rule(fn () => Rule::exists('categorias', 'id')
                        ->where('restaurante_id', Filament::getTenant()?->getKey())
                        ->whereNull('deleted_at')),
                TextInput::make('precio')
                    ->label('Precio regular')
                    ->placeholder('0.00')
                    ->helperText('Precio base del platillo.')
                    ->numeric()->minValue(0)->maxValue(99999999.99)->step(0.01)->required(),
                TextInput::make('precio_oferta')
                    ->label('Precio de oferta')
                    ->placeholder('0.00')
                    ->helperText('Si se define, sustituye al precio regular y muestra el anterior tachado.')
                    ->numeric()->minValue(0)->maxValue(99999999.99)->step(0.01)->lte('precio'),
                ImagenRestauranteUpload::make('imagen', 'productos')->label('Fotografía del platillo'),
                TextInput::make('orden')
                    ->label('Orden de visualización')
                    ->placeholder('0')
                    ->helperText('Posición numérica para ordenar el platillo dentro de su categoría.')
                    ->numeric()->integer()->minValue(0)->maxValue(1000000)->default(0)->required(),
                Toggle::make('disponible')
                    ->label('Disponible')
                    ->helperText('Si se desmarca, el platillo se marca como agotado.')
                    ->default(true),
                Toggle::make('visible')
                    ->label('Visible en el menú')
                    ->helperText('Determina si el platillo aparece en la carta pública.')
                    ->default(true),
                Toggle::make('destacado')
                    ->label('Recomendado del chef')
                    ->helperText('Muestra este platillo en el carrusel de destacados del menú.'),
                Select::make('etiquetas')
                    ->label('Etiquetas dietéticas')
                    ->placeholder('Selecciona etiquetas dietéticas')
                    ->relationship('etiquetas', 'codigo')
                    ->multiple()
                    ->preload(),
                TraduccionesRepeater::make('traducciones', 150)->columnSpanFull(),
                Section::make('Variantes')
                    ->description('Si el platillo tiene variantes, el menú muestra el precio de cada una en lugar del precio base.')
                    ->schema([
                        Repeater::make('variantes')
                            ->label('Variantes')
                            ->relationship('variantes')
                            ->schema([
                                TextInput::make('precio')->label('Precio')->placeholder('0.00')->numeric()->minValue(0)->maxValue(99999999.99)->step(0.01)->required(),
                                TextInput::make('orden')->label('Orden')->placeholder('0')->numeric()->integer()->minValue(0)->maxValue(1000000)->default(0)->required(),
                                Toggle::make('disponible')->label('Disponible')->default(true),
                                TraduccionesRepeater::make('traducciones', 80, conDescripcion: false)->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar variante'),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function categorias(): array
    {
        return Categoria::query()
            ->with(['traducciones', 'restaurante'])
            ->orderBy('orden')
            ->get()
            ->mapWithKeys(fn (Categoria $categoria): array => [$categoria->getKey() => $categoria->traduccion()?->nombre ?? "#{$categoria->getKey()}"])
            ->all();
    }
}
