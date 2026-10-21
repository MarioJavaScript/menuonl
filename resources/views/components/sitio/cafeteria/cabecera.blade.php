@props(['d', 'actual' => 'inicio'])
@php
    $inicioUrl = route('sitio.publico', ['slug' => $d['r']->slug]);
    $hayVisita = filled($d['direccion']) || filled($d['mapa_url']) || collect($d['horarios'])->contains('cerrado', false);
    $lineas = [
        'ubicacion' => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>',
        'telefono' => '<path d="M5 4h3.5l1.8 4.4-2.2 1.4a11 11 0 0 0 5.1 5.1l1.4-2.2L19 14.5V18a2 2 0 0 1-2 2A13 13 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'whatsapp' => '<path d="M4 20l1.3-4.2A8 8 0 1 1 8.4 19z"/><path d="M9.2 8.8c.2 2.4 2.6 4.8 5.6 5.6l1.2-1.4-2-1-.8.8c-.9-.4-1.8-1.3-2.2-2.2l.8-.8-1-2z"/>',
    ];
@endphp
<header class="cal-cabecera">
    <a class="cal-cabecera__marca" href="{{ $inicioUrl }}">{{ $d['r']->nombre }}</a>
    <nav class="cal-cabecera__nav" aria-label="Principal">
        <ul class="cal-cabecera__lista">
            <li><a class="cal-cabecera__enlace" href="{{ $inicioUrl }}" @if ($actual === 'inicio') aria-current="page" @endif>Inicio</a></li>
            <li><a class="cal-cabecera__enlace" href="{{ $d['menu_url'] }}" @if ($actual === 'menu') aria-current="page" @endif>Menú</a></li>
            @if ($d['reservas_activas'] ?? false)<li><a class="cal-cabecera__enlace" href="{{ $actual === 'inicio' ? '#reservar' : $inicioUrl.'#reservar' }}">Reservar</a></li>@endif
        </ul>
    </nav>
    @if ($hayVisita || filled($d['telefono_enlace']) || filled($d['whatsapp_digitos']))
        <ul class="cal-cabecera__iconos">
            @if ($hayVisita)
                <li><a class="cal-icono" href="#contacto" aria-label="Ubicación y horario"><svg class="cal-icono__svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{!! $lineas['ubicacion'] !!}</svg></a></li>
            @endif
            @if (filled($d['telefono_enlace']))
                <li><a class="cal-icono" href="{{ $d['telefono_enlace'] }}" aria-label="Llamar"><svg class="cal-icono__svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{!! $lineas['telefono'] !!}</svg></a></li>
            @endif
            @if (filled($d['whatsapp_digitos']))
                <li><a class="cal-icono" href="https://wa.me/{{ $d['whatsapp_digitos'] }}" rel="noopener" aria-label="Escribir por WhatsApp"><svg class="cal-icono__svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{!! $lineas['whatsapp'] !!}</svg></a></li>
            @endif
        </ul>
    @endif
</header>
