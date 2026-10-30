{{-- Enlace al aviso de privacidad bajo el botón de reservar. Solo aparece cuando /privacidad ya no tiene texto pendiente (MarcadoresPendientes). --}}
@if (! app(\App\Services\MarcadoresPendientes::class)->privacidadPendiente())
    <p class="{{ $clase }}"><small>Consulta el <a href="{{ route('privacidad') }}">Aviso de privacidad</a>.</small></p>
@endif
