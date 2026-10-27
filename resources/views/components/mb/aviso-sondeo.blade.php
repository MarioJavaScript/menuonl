@props(['red', 'sesion', 'entrar'])
{{-- Aviso de un sondeo que falló por la red o por la sesión caducada. Lo muestra y oculta partials/sondeo-con-sesion (docs/integracion-dashboard.md, secciones 18 y 20). --}}
<div
    {{ $attributes->class(['mb-sondeo__aviso']) }}
    data-sondeo-aviso
    data-texto-red="{{ $red }}"
    data-texto-sesion="{{ $sesion }}"
    wire:ignore
    hidden
>
    <x-mb.aviso tono="atencion" :anuncia="false">
        <span data-sondeo-aviso-texto></span>
        <x-slot:accion>
            <a class="mb-sondeo__entrar" href="{{ filament()->getLoginUrl() ?? url('/admin') }}" data-sondeo-entrar hidden>{{ $entrar }}</a>
        </x-slot:accion>
    </x-mb.aviso>
</div>
