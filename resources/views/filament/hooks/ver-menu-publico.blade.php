@props(['url', 'sitioUrl' => null, 'enMenuLateral' => false])

{{-- Accesos visibles al sitio y al menú públicos desde cualquier pantalla del panel (DISEÑO.md): "Ver mi sitio" (solo si el plan tiene sitio) y "Ver mi menú".
     Junto al menú de usuario en pantallas anchas; en el celular la barra superior solo lleva "Ver mi menú" y los dos viven al pie del menú lateral. --}}
<div @class(['flex items-center gap-2', 'me-3' => ! $enMenuLateral, 'flex-col items-stretch p-4 lg:hidden' => $enMenuLateral])>
    @if ($sitioUrl)
        <x-filament::button
            tag="a"
            :href="$sitioUrl"
            target="_blank"
            rel="noopener noreferrer"
            size="sm"
            color="gray"
            icon="heroicon-o-arrow-top-right-on-square"
            icon-position="after"
            @class(['max-lg:hidden' => ! $enMenuLateral])
        >
            {{ __('panel.enlaces.ver_sitio') }}
            <span class="sr-only">{{ __('panel.enlaces.abre_en_otra_pestana') }}</span>
        </x-filament::button>
    @endif
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
