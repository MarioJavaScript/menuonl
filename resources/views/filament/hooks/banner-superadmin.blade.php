{{-- Banner de superadmin (PR 4). Solo se renderiza para un superadmin dentro de un restaurante; el servicio decide, no el CSS. --}}
<x-mb.banner class="mb-banner--fijo" :etiqueta="__('panel.banner_superadmin.etiqueta', ['nombre' => $restaurante->nombre])">
    <span class="mb-banner__contenido">
        <span class="mb-banner__prefijo" aria-hidden="true">
            <span class="mb-banner__largo">{{ __('panel.banner_superadmin.prefijo') }}</span>
            <span class="mb-banner__corto">{{ __('panel.banner_superadmin.prefijo_corto') }}</span>
        </span>
        <strong class="mb-banner__nombre" title="{{ $restaurante->nombre }}">{{ $restaurante->nombre }}</strong>
        <x-mb.glifo-plan :plan="$restaurante->plan" class="mb-banner__plan" />
        @if ($restaurante->estaPausado())
            <strong class="mb-banner__estado" data-fuera-de-servicio>· {{ __('panel.banner_superadmin.fuera_de_servicio') }}</strong>
        @endif
    </span>
    <x-slot:accion>
        <a href="{{ $urlDeVuelta }}" aria-label="{{ __('panel.banner_superadmin.volver') }}">
            <span class="mb-banner__largo" aria-hidden="true">{{ __('panel.banner_superadmin.volver') }}</span>
            <span class="mb-banner__corto" aria-hidden="true">{{ __('panel.banner_superadmin.volver_corto') }}</span>
        </a>
    </x-slot:accion>
</x-mb.banner>
