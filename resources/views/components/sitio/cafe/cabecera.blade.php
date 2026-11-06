@props(['d', 'actual' => 'inicio'])
@php
    /*
     * Cabecera de la plantilla P. Con logo, el logo (tamaño máximo fijo en la hoja) junto al nombre; sin logo, solo el nombre.
     * Galería y Horario solo aparecen si su sección existe; Reservar, solo con reservas activas; "Pedir ahora" (botón de acento),
     * solo en el sitio y con pedidos habilitados.
     */
    $inicioUrl = route('sitio.publico', ['slug' => $d['r']->slug]);
    $ancla = fn (string $id): string => $actual === 'inicio' ? '#'.$id : $inicioUrl.'#'.$id;
    $hayGaleria = ($d['galeria_fotos'] ?? []) !== [];
    $hayVisita = collect($d['horarios'])->contains('cerrado', false) || filled($d['direccion']) || filled($d['mapa_url']);
@endphp
<header class="caf-encabezado">
    <div class="caf-encabezado__interior">
        <a class="caf-marca" href="{{ $inicioUrl }}">
            @if (filled($d['r']->logo))<x-sitio.imagen :ruta="$d['r']->logo" alt="" :ancho="92" :alto="92" sizes="46px" :prioridad="true" class="caf-marca__logo" />@endif
            <span>{{ $d['r']->nombre }}</span>
        </a>
        <nav class="caf-nav" aria-label="Principal">
            <ul class="caf-nav__lista">
                <li><a class="caf-nav__enlace" href="{{ $d['menu_url'] }}" @if ($actual === 'menu') aria-current="page" @endif>Menú</a></li>
                @if ($hayGaleria)<li><a class="caf-nav__enlace" href="{{ $ancla('galeria') }}">Galería</a></li>@endif
                @if ($hayVisita)<li><a class="caf-nav__enlace" href="{{ $ancla('visitanos') }}">Horario</a></li>@endif
                @if ($d['reservas_activas'] ?? false)<li><a class="caf-nav__enlace" href="{{ $ancla('reservar') }}">Reservar</a></li>@endif
                @if ($actual === 'inicio' && $d['r']->pedidosHabilitados())<li><a class="caf-nav__enlace caf-nav__pedir" href="{{ $d['menu_url'] }}">Pedir ahora</a></li>@endif
            </ul>
        </nav>
    </div>
</header>
