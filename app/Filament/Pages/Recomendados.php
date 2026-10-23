<?php

namespace App\Filament\Pages;

use App\Models\Categoria;
use App\Models\Producto;
use App\Services\RecomendadosDelSitio;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Recomendados: el dueño marca o desmarca los platillos destacados y ve cuántos muestra el sitio y cuál sale como principal.
 * Lo ve y lo cambia quien hoy puede editar platillos (la política de Producto no cambia).
 */
class Recomendados extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'recomendados';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('panel.grupos.menu');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.recomendados.nav');
    }

    public static function canAccess(): bool
    {
        return Filament::getTenant() !== null && (auth()->user()?->can('viewAny', Producto::class) ?? false);
    }

    public function getTitle(): string
    {
        return __('panel.recomendados.titulo');
    }

    public function getSubheading(): ?string
    {
        return __('panel.recomendados.subtitulo');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.recomendados-resumen')
                ->viewData(fn (): array => [
                    'resumen' => app(RecomendadosDelSitio::class)->para(Filament::getTenant()),
                    'nombreDe' => fn (Producto $producto): string => $producto->traduccion()?->nombre ?? __('panel.recomendados.sin_nombre'),
                ]),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Producto::query()->with(['traducciones', 'categoria.traducciones']))
            ->columns([
                ImageColumn::make('imagen')
                    ->label(__('panel.recomendados.foto'))
                    ->disk('public')
                    ->square()
                    ->size(48)
                    ->visibleFrom('sm'),
                TextColumn::make('nombre')
                    ->label(__('panel.recomendados.platillo'))
                    ->weight('semibold')
                    ->state(fn (Producto $record): string => $record->traduccion()?->nombre ?? __('panel.recomendados.sin_nombre'))
                    ->description(fn (Producto $record): ?string => $record->categoria?->traduccion()?->nombre)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('traducciones', fn (Builder $q): Builder => $q->where('nombre', 'like', "%{$search}%"))),
                TextColumn::make('estado')
                    ->label(__('panel.recomendados.estado'))
                    ->badge()
                    ->color('warning')
                    ->state(fn (Producto $record): ?string => match (true) {
                        ! $record->visible => __('panel.recomendados.oculto'),
                        ! $record->disponible => __('panel.recomendados.agotado'),
                        default => null,
                    }),
                ToggleColumn::make('destacado')
                    ->label(__('panel.recomendados.recomendado'))
                    ->updateStateUsing(function (Producto $record, bool $state): bool {
                        Gate::authorize('update', $record);

                        $record->update(['destacado' => $state]);

                        return $state;
                    })
                    ->afterStateUpdated(fn (Producto $record, bool $state) => Notification::make()
                        ->success()
                        ->title(($record->traduccion()?->nombre ?? __('panel.recomendados.sin_nombre')).' '.($state ? __('panel.recomendados.ahora_recomendado') : __('panel.recomendados.ya_no_recomendado')))
                        ->send()),
            ])
            ->stackedOnMobile()
            ->defaultSort('orden')
            ->filters([
                Filter::make('solo_recomendados')
                    ->label(__('panel.recomendados.solo_recomendados'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('destacado', true)),
                SelectFilter::make('categoria_id')
                    ->label(__('panel.recomendados.categoria'))
                    ->placeholder(__('panel.recomendados.todas'))
                    ->options(fn (): array => Categoria::query()
                        ->with('traducciones')
                        ->orderBy('orden')
                        ->get()
                        ->mapWithKeys(fn (Categoria $c): array => [$c->id => $c->traduccion()?->nombre ?? __('panel.recomendados.sin_nombre')])
                        ->all()),
            ])
            ->emptyStateHeading(__('panel.recomendados.vacio_titulo'))
            ->emptyStateDescription(__('panel.recomendados.vacio_descripcion'))
            ->emptyStateIcon('heroicon-o-star');
    }
}
