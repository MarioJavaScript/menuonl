{{-- Barra inferior del panel en el celular (DISEÑO.md): hasta 4 accesos según lo que la persona puede abrir; "Más" abre el menú lateral completo. --}}
<nav class="mo-barra-inferior" aria-label="{{ __('panel.barra.nombre') }}" style="grid-template-columns: repeat({{ count($accesos) + 1 }}, minmax(0, 1fr))">
    @foreach ($accesos as $acceso)
        <a
            href="{{ $acceso['url'] }}"
            @if ($acceso['nombre'] !== $acceso['etiqueta']) aria-label="{{ $acceso['nombre'] }}" @endif
            @if ($acceso['activa']) aria-current="page" @endif
        >
            <x-filament::icon :icon="$acceso['icono']" />
            <span>{{ $acceso['etiqueta'] }}</span>
        </a>
    @endforeach
    <button
        type="button"
        x-data
        x-on:click="$store.sidebar.open()"
        x-bind:aria-expanded="$store.sidebar.isOpen"
        aria-controls="fi-main-sidebar"
        aria-label="{{ __('panel.barra.mas_opciones') }}"
    >
        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedEllipsisHorizontalCircle" />
        <span>{{ __('panel.barra.mas') }}</span>
    </button>
</nav>
