@if ($whatsapp_digitos || $telefono_enlace)
    <div class="reservar-contacto">
        @if ($whatsapp_digitos)
            <a class="btn" href="https://wa.me/{{ $whatsapp_digitos }}?text={{ rawurlencode('Hola, quiero reservar en '.$r->nombre.'.') }}" rel="noopener">Reservar por WhatsApp</a>
        @endif
        @if ($telefono_enlace)
            <a class="btn btn-alt" href="{{ $telefono_enlace }}">Llamar</a>
        @endif
    </div>
@endif
