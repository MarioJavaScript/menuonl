@php
    use App\Filament\Pages\Inicio;
    use App\Filament\Resources\Categorias\CategoriaResource;
    use App\Filament\Resources\Horarios\HorarioResource;
    use App\Filament\Resources\Productos\ProductoResource;
    use Filament\Support\Icons\Heroicon;

    // Barra inferior del panel en el celular (DISEÑO.md): las 4 tareas más frecuentes y "Más" abre el menú completo.
    $opciones = array_filter([
        ['etiqueta' => 'Inicio', 'url' => Inicio::getUrl(), 'icono' => Heroicon::OutlinedHome, 'activa' => request()->routeIs(Inicio::getRouteName()), 'visible' => true],
        ['etiqueta' => 'Platillos', 'url' => ProductoResource::getUrl('index'), 'icono' => Heroicon::OutlinedCake, 'activa' => request()->routeIs('filament.admin.resources.productos.*'), 'visible' => ProductoResource::canViewAny()],
        ['etiqueta' => 'Categorías', 'url' => CategoriaResource::getUrl('index'), 'icono' => Heroicon::OutlinedRectangleStack, 'activa' => request()->routeIs('filament.admin.resources.categorias.*'), 'visible' => CategoriaResource::canViewAny()],
        ['etiqueta' => 'Horario', 'url' => HorarioResource::getUrl('index'), 'icono' => Heroicon::OutlinedClock, 'activa' => request()->routeIs('filament.admin.resources.horarios.*'), 'visible' => HorarioResource::canViewAny()],
    ], fn (array $opcion): bool => $opcion['visible']);
@endphp

<nav class="mo-barra-inferior" aria-label="Accesos rápidos" style="grid-template-columns: repeat({{ count($opciones) + 1 }}, minmax(0, 1fr))">
    @foreach ($opciones as $opcion)
        <a href="{{ $opcion['url'] }}" @if ($opcion['activa']) aria-current="page" @endif>
            <x-filament::icon :icon="$opcion['icono']" />
            <span>{{ $opcion['etiqueta'] }}</span>
        </a>
    @endforeach
    <button type="button" x-data x-on:click="$store.sidebar.open()" aria-label="Más opciones del menú">
        <x-filament::icon :icon="Heroicon::OutlinedEllipsisHorizontalCircle" />
        <span>Más</span>
    </button>
</nav>
