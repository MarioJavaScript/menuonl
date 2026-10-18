@props(['url'])

<div class="flex items-center me-3">
    <x-filament::button
        tag="a"
        :href="$url"
        target="_blank"
        rel="noopener noreferrer"
        size="sm"
        color="warning"
        outlined
        icon="heroicon-o-arrow-top-right-on-square"
        title="Abrir el menú digital público en una nueva pestaña"
    >
        <span class="hidden sm:inline">Ver menú público</span>
        <span class="sm:hidden">Menú</span>
    </x-filament::button>
</div>
