<?php

namespace App\Filament\Resources\Productos\Schemas;

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
                    ->options(fn (): array => self::categorias())
                    ->required()
                    ->searchable()
                    ->rule(fn () => Rule::exists('categorias', 'id')
                        ->where('restaurante_id', Filament::getTenant()?->getKey())
                        ->whereNull('deleted_at')),
                TextInput::make('precio')
                    ->label('Precio')
                    ->numeric()->minValue(0)->maxValue(99999999.99)->step(0.01)->required(),
                TextInput::make('precio_oferta')
                    ->label('Precio de oferta')
                    ->numeric()->minValue(0)->maxValue(99999999.99)->step(0.01)->lte('precio'),
                TextInput::make('orden')->label('Orden')->numeric()->integer()->minValue(0)->maxValue(1000000)->default(0)->required(),
                Toggle::make('disponible')->label('Disponible')->default(true),
                Toggle::make('visible')->label('Visible en el menú')->default(true),
                Toggle::make('destacado')->label('Destacado (Recomendados)'),
                Select::make('etiquetas')
                    ->label('Etiquetas')
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
                                TextInput::make('precio')->label('Precio')->numeric()->minValue(0)->maxValue(99999999.99)->step(0.01)->required(),
                                TextInput::make('orden')->label('Orden')->numeric()->integer()->minValue(0)->maxValue(1000000)->default(0)->required(),
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
