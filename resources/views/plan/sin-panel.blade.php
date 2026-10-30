<x-layout.aviso-de-acceso :titulo="__('panel.plan_sin_panel.titulo')">
    <h1>{{ __('panel.plan_sin_panel.titulo') }}</h1>

    @if (count($restaurantes) === 1)
        <p>{{ __('panel.plan_sin_panel.uno', ['restaurante' => $restaurantes[0]]) }}</p>
    @else
        <p>{{ __('panel.plan_sin_panel.varios') }}</p>
        <ul class="restaurantes">
            @foreach ($restaurantes as $nombre)
                <li>{{ $nombre }}</li>
            @endforeach
        </ul>
    @endif

    <p>{{ __('panel.plan_sin_panel.proveedor') }}</p>

    @if ($enlaceWhatsapp !== null)
        <p class="suave">{{ __('panel.plan_sin_panel.con_whatsapp') }}</p>
    @else
        <p class="suave">{{ __('panel.plan_sin_panel.sin_whatsapp') }}</p>
    @endif

    <div class="acciones">
        @if ($enlaceWhatsapp !== null)
            <a class="boton boton-principal" href="{{ $enlaceWhatsapp }}" target="_blank" rel="noopener noreferrer">{{ __('panel.plan_sin_panel.boton_whatsapp') }}</a>
        @endif
        <form method="POST" action="{{ $urlCerrarSesion }}">
            @csrf
            <button type="submit" class="boton boton-secundario">{{ __('panel.plan_sin_panel.cerrar_sesion') }}</button>
        </form>
    </div>
</x-layout.aviso-de-acceso>
