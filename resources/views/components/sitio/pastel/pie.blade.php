@props(['d'])
@php
    $inicioUrl = route('sitio.publico', ['slug' => $d['r']->slug]);
@endphp
<footer class="pas-franja pas-franja--arriba pas-pie">
    <div class="pas-contenedor">
        <div class="pas-pie__rejilla">
            <div>
                <p class="pas-cursiva">{{ $d['r']->nombre }}</p>
                @if (filled($d['direccion']))<p>{{ $d['direccion'] }}</p>@endif
            </div>
            <nav aria-label="Pie de página">
                <ul class="pas-pie__lista">
                    <li><a href="{{ $inicioUrl }}">Inicio</a></li>
                    <li><a href="{{ $d['menu_url'] }}">Menú</a></li>
                    @if ($d['reservas_activas'] ?? false)<li><a href="{{ $inicioUrl }}#reservar">Reservar</a></li>@endif
                    @if (filled($d['instagram_url']))<li><a href="{{ $d['instagram_url'] }}" rel="noopener" aria-label="Instagram de {{ $d['r']->nombre }}"><svg class="pas-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="4" width="16" height="16" rx="4.5"/><circle cx="12" cy="12" r="3.6"/><path d="M16.8 7.2h.01"/></svg>Instagram</a></li>@endif
                    @if (filled($d['facebook_url']))<li><a href="{{ $d['facebook_url'] }}" rel="noopener" aria-label="Facebook de {{ $d['r']->nombre }}"><svg class="pas-icono" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 8.5V7a1.5 1.5 0 0 1 1.5-1.5H17V2.5h-2.5A3.5 3.5 0 0 0 11 6v2.5H8.5v3H11V21h3v-9.5h2.6l.4-3z"/></svg>Facebook</a></li>@endif
                </ul>
            </nav>
        </div>
        <p class="pas-pie__copia">Sitio creado con Menuonl</p>
    </div>
</footer>
