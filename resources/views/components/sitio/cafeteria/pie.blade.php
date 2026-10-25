@props(['d'])
@php
    $inicioUrl = route('sitio.publico', ['slug' => $d['r']->slug]);
    $hayContacto = filled($d['direccion']) || filled($d['mapa_url']) || filled($d['telefono_enlace']) || filled($d['whatsapp_digitos']) || filled($d['instagram_url']) || filled($d['facebook_url']) || collect($d['horarios'])->contains('cerrado', false);
@endphp
<footer class="cal-pie">
    <div class="cal-pie__interior">
        <p class="cal-pie__marca"><span>{{ $d['r']->nombre }}</span>@if (filled($d['r']->eslogan))<span class="cal-pie__descriptor">{{ $d['r']->eslogan }}</span>@endif</p>
        <nav aria-label="Pie de página">
            <ul class="cal-pie__lista">
                <li><a class="cal-pie__enlace" href="{{ $inicioUrl }}">Inicio</a></li>
                <li><a class="cal-pie__enlace" href="{{ $d['menu_url'] }}">Menú</a></li>
                @if ($hayContacto)<li><a class="cal-pie__enlace" href="#contacto">Contacto</a></li>@endif
            </ul>
        </nav>
        <p class="cal-pie__nota">Sitio creado con {{ config('app.name') }}</p>
    </div>
</footer>
