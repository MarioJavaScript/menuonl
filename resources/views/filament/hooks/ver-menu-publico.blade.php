@props(['url'])

{{-- Único acceso al menú público desde el panel (DISEÑO.md): "Ver mi menú", en la cabecera. --}}
<div class="flex items-center me-3">
    <x-filament::button
        tag="a"
        :href="$url"
        target="_blank"
        rel="noopener noreferrer"
        size="sm"
        color="gray"
        icon="heroicon-o-arrow-top-right-on-square"
        icon-position="after"
    >
        Ver mi menú
        <span class="sr-only">(se abre en otra pestaña)</span>
    </x-filament::button>
</div>
