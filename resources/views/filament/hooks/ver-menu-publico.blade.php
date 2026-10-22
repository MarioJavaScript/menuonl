@props(['url', 'sitioUrl'])

{{-- Accesos visibles al sitio y al menú públicos desde cualquier pantalla del panel (DISEÑO.md): "Ver mi sitio" y "Ver mi menú". --}}
<div class="flex items-center gap-2 me-3">
    <x-filament::button
        tag="a"
        :href="$sitioUrl"
        target="_blank"
        rel="noopener noreferrer"
        size="sm"
        color="gray"
        icon="heroicon-o-arrow-top-right-on-square"
        icon-position="after"
    >
        {{ __('panel.enlaces.ver_sitio') }}
        <span class="sr-only">{{ __('panel.enlaces.abre_en_otra_pestana') }}</span>
    </x-filament::button>
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
        {{ __('panel.enlaces.ver_menu') }}
        <span class="sr-only">{{ __('panel.enlaces.abre_en_otra_pestana') }}</span>
    </x-filament::button>
</div>
