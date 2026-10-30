<x-layout.aviso-de-acceso :titulo="__('panel.cuenta_pausada.titulo')">
    <h1>{{ __('panel.cuenta_pausada.titulo') }}</h1>

    @if (count($restaurantes) === 1)
        <p>{{ __('panel.cuenta_pausada.uno', ['restaurante' => $restaurantes[0]]) }}</p>
    @else
        <p>{{ __('panel.cuenta_pausada.varios') }}</p>
        <ul class="restaurantes">
            @foreach ($restaurantes as $nombre)
                <li>{{ $nombre }}</li>
            @endforeach
        </ul>
    @endif

    @foreach ($mensajes as $mensaje)
        <p>{{ $mensaje }}</p>
    @endforeach

    @if ($enlaceWhatsapp !== null)
        <p class="suave">{{ __('panel.cuenta_pausada.con_whatsapp') }}</p>
    @else
        <p class="suave">{{ __('panel.cuenta_pausada.sin_whatsapp') }}</p>
    @endif

    <div class="acciones">
        @if ($enlaceWhatsapp !== null)
            <a class="boton boton-principal" href="{{ $enlaceWhatsapp }}" target="_blank" rel="noopener noreferrer">{{ __('panel.cuenta_pausada.boton_whatsapp') }}</a>
        @endif
        <form method="POST" action="{{ $urlCerrarSesion }}">
            @csrf
            <button type="submit" class="boton boton-secundario">{{ __('panel.cuenta_pausada.cerrar_sesion') }}</button>
        </form>
    </div>
</x-layout.aviso-de-acceso>
