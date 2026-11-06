@props(['d'])
@php
    $inicioUrl = route('sitio.publico', ['slug' => $d['r']->slug]);
@endphp
<footer class="caf-pie">
    <div class="caf-contenedor caf-pie__contenido">
        <p class="caf-pie__nombre">{{ $d['r']->nombre }}</p>
        <nav aria-label="Pie de página">
            <ul class="caf-pie__lista">
                <li><a href="{{ $inicioUrl }}">Inicio</a></li>
                <li><a href="{{ $d['menu_url'] }}">Menú</a></li>
                @if ($d['reservas_activas'] ?? false)<li><a href="{{ $inicioUrl }}#reservar">Reservar</a></li>@endif
            </ul>
        </nav>
        <p>Sitio creado con {{ config('app.name') }}</p>
    </div>
</footer>
