<?php

namespace App\Filament\Components;

use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Services\Catalogos;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Repeater for the *_traducciones relationships (RF-17).
 *
 * Languages are limited to the ones active in the current restaurant, and the restaurant's
 * default language is mandatory so the public menu always has a fallback.
 */
class TraduccionesRepeater
{
    public static function make(string $relacion, int $maxNombre, bool $conDescripcion = true): Repeater
    {
        $campos = [
            Select::make('idioma')
                ->label('Idioma')
                ->options(fn (): array => self::idiomasActivos())
                ->in(fn (): array => array_keys(self::idiomasActivos()))
                ->required()
                ->distinct(),
            TextInput::make('nombre')
                ->label('Nombre')
                ->placeholder('Nombre en este idioma')
                ->required()
                ->maxLength($maxNombre),
        ];

        if ($conDescripcion) {
            $campos[] = Textarea::make('descripcion')
                ->label('Descripción (opcional)')
                ->placeholder('Ingredientes, preparación o notas breves...')
                ->maxLength(2000)
                ->rows(2);
        }

        return Repeater::make($relacion)
            ->label('Traducciones')
            ->relationship($relacion)
            ->schema($campos)
            ->default(fn (): array => [
                [
                    'idioma' => Filament::getTenant()?->idioma_default ?? 'es',
                    'nombre' => '',
                    'descripcion' => null,
                ],
            ])
            ->itemLabel(fn (array $state): ?string => isset($state['idioma'])
                    ? (Catalogos::idiomas()[$state['idioma']] ?? $state['idioma']).
                      (isset($state['nombre']) && filled($state['nombre']) ? " — {$state['nombre']}" : '')
                    : null
            )
            ->minItems(1)
            ->addActionLabel('Agregar otro idioma')
            ->rule(fn (): Closure => self::exigirIdiomaPrincipal());
    }

    /**
     * @return array<string, string>
     */
    public static function idiomasActivos(): array
    {
        $restaurante = Filament::getTenant();

        if (! $restaurante instanceof Restaurante) {
            return [];
        }

        $activos = RestauranteIdioma::query()->orderBy('orden')->pluck('idioma')->all();
        $activos = array_unique([$restaurante->idioma_default, ...$activos]);

        return collect($activos)
            ->mapWithKeys(fn (string $idioma): array => [$idioma => Catalogos::idiomas()[$idioma] ?? $idioma])
            ->all();
    }

    private static function exigirIdiomaPrincipal(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $principal = Filament::getTenant()?->idioma_default;
            $idiomas = collect(is_array($value) ? $value : [])->pluck('idioma');

            if ($principal !== null && ! $idiomas->contains($principal)) {
                $fail("Falta la traducción en el idioma principal del restaurante ({$principal}).");
            }
        };
    }
}
