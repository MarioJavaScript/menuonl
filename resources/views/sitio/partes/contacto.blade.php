<ul class="contacto">
    @if ($direccion)
        <li class="direccion">{{ $direccion }}</li>
    @endif
    @if ($telefono)
        <li>@if ($telefono_enlace)<a href="{{ $telefono_enlace }}">{{ $telefono }}</a>@else{{ $telefono }}@endif</li>
    @endif
    @if ($whatsapp_digitos)
        <li><a href="https://wa.me/{{ $whatsapp_digitos }}" rel="noopener">WhatsApp</a></li>
    @endif
    @if ($instagram_url)
        <li><a href="{{ $instagram_url }}" rel="noopener">Instagram</a></li>
    @endif
    @if ($facebook_url)
        <li><a href="{{ $facebook_url }}" rel="noopener">Facebook</a></li>
    @endif
    @if ($mapa_url)
        <li><a href="{{ $mapa_url }}" rel="noopener">Cómo llegar</a></li>
    @endif
</ul>
