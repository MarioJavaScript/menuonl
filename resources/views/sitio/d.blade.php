@php
    /*
     * Plantilla D · Editorial oscuro. Todo el contenido sale de los datos del restaurante; una sección sin datos se omite.
     * El acento solo entra por Restaurante::colorAcentoSeguro() (hex de 6 dígitos) y solo si es legible sobre el fondo oscuro.
     */
    $contraste = function (string $a, string $b): float {
        $luz = function (string $hex): float {
            [$r, $g, $bl] = array_map(fn (string $par): float => hexdec($par) / 255, str_split(ltrim($hex, '#'), 2));
            $lineal = fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

            return 0.2126 * $lineal($r) + 0.7152 * $lineal($g) + 0.0722 * $lineal($bl);
        };
        [$claro, $oscuro] = [max($luz($a), $luz($b)), min($luz($a), $luz($b))];

        return ($claro + 0.05) / ($oscuro + 0.05);
    };

    $acentoPropio = $r->colorAcentoSeguro();
    $acento = $acentoPropio !== null && $contraste($acentoPropio, '#14120f') >= 4.5 ? $acentoPropio : '#c9a24a';
    $textoCandidato = $acento === $acentoPropio ? ($r->colorAcentoTexto() ?? '#0c0b0a') : '#0c0b0a';
    $acentoTexto = $contraste($textoCandidato, $acento) >= $contraste('#0c0b0a', $acento) ? $textoCandidato : '#0c0b0a';

    $nombre = trim((string) $r->nombre);
    $inicioNombre = $nombre;
    $ultimaPalabra = null;
    if (preg_match('/\A(.*\S)\s+(\S+)\z/su', $nombre, $partes) === 1) {
        [, $inicioNombre, $ultimaPalabra] = $partes;
    }

    $hayHorario = collect($horarios)->contains('cerrado', false);
    $hayContacto = filled($direccion) || filled($telefono) || filled($whatsapp_digitos) || filled($instagram_url) || filled($facebook_url) || filled($mapa_url);
    $hayContactoRapido = filled($whatsapp_digitos) || filled($telefono_enlace);
    $hayReserva = $reservas_activas || $hayContactoRapido;
    $hayHistoria = filled($r->descripcion);
@endphp
<x-sitio.layout :r="$r" plantilla="d" :descripcion="$r->descripcion">
<div class="d-raiz" style="--acento: {{ $acento }}; --acento-texto: {{ $acentoTexto }};">
<a class="d-saltar" href="#contenido">Saltar al contenido</a>

<header class="d-cab"><div class="d-wrap d-cab-fila">
    <a class="d-logo" href="#inicio">{{ $nombre }}</a>
    <nav class="d-nav" aria-label="Principal">
        @if ($hayHistoria)<a href="#historia">Historia</a>@endif
        <a href="{{ $menu_url }}">Menú</a>
        @if ($hayHorario)<a href="#horario">Horario</a>@endif
        @if ($hayContacto)<a href="#contacto">Contacto</a>@endif
    </nav>
    @if ($reservas_activas)<a class="d-btn d-btn-chico" href="#reservar">Reservar</a>@endif
</div></header>

<main id="contenido">
<section id="inicio" class="d-portada" aria-labelledby="d-titulo"><div class="d-wrap d-portada-rejilla">
    <div class="d-portada-texto">
        @if ($hayHorario)
            <p class="d-estado {{ $esta_abierto ? 'abierto' : 'cerrado' }}"><span class="d-punto" aria-hidden="true"></span>{{ $esta_abierto ? 'Abierto ahora' : 'Cerrado ahora' }}</p>
        @endif
        <h1 id="d-titulo">{{ $inicioNombre }}@if ($ultimaPalabra !== null) <em>{{ $ultimaPalabra }}</em>@endif</h1>
        @if (filled($r->eslogan))<p class="d-eslogan">{{ $r->eslogan }}</p>@endif
        <div class="d-acciones">
            <a class="d-btn" href="{{ $menu_url }}">Ver menú</a>
            @if ($reservas_activas)<a class="d-btn d-btn-linea" href="#reservar">Reservar</a>@endif
        </div>
    </div>
    <div class="d-portada-foto">
        @if (filled($hero))
            <x-sitio.imagen :ruta="$hero" :alt="'Portada de '.$nombre" :ancho="1600" :alto="900" sizes="(min-width:900px) 50vw, 100vw" :prioridad="true" />
        @else
            <div class="d-vacio" aria-hidden="true"></div>
        @endif
    </div>
</div></section>

@if ($hayHistoria)
<section id="historia" class="d-seccion d-banda" aria-labelledby="d-historia"><div class="d-wrap d-estrecho">
    <span class="d-regla" aria-hidden="true"></span>
    <h2 id="d-historia">Historia</h2>
    <p class="d-historia-texto">{{ $r->descripcion }}</p>
</div></section>
@endif

@if ($destacados)
<section id="recomendados" class="d-seccion" aria-labelledby="d-recomendados"><div class="d-wrap">
    <span class="d-regla" aria-hidden="true"></span>
    <h2 id="d-recomendados">Recomendados</h2>
    <ul class="d-tarjetas">
    @foreach ($destacados as $p)
        <li @class(['d-tarjeta', 'agotado' => $p['agotado']])>
            <div class="d-tarjeta-foto">
                @if (filled($p['imagen']))
                    <x-sitio.imagen :ruta="$p['imagen']" :alt="$p['nombre']" :ancho="480" :alto="600" sizes="(min-width:900px) 30vw, 100vw" />
                @else
                    <div class="d-vacio" aria-hidden="true"></div>
                @endif
            </div>
            <div class="d-tarjeta-cuerpo">
                @if (filled($p['categoria']))<p class="d-etq">{{ $p['categoria'] }}</p>@endif
                <h3>{{ $p['nombre'] }}</h3>
                @if (filled($p['descripcion']))<p class="d-desc">{{ $p['descripcion'] }}</p>@endif
                <p class="d-precio">
                    @if ($p['precio_anterior'] !== null)<s class="d-precio-antes"><span class="sr-only">Precio anterior </span>{{ $p['precio_anterior'] }}</s> @endif
                    <span>{{ $p['precio'] }}</span>
                </p>
                @if ($p['agotado'] || $p['etiquetas'])
                    <ul class="d-marcas">
                        @if ($p['agotado'])<li>Agotado</li>@endif
                        @foreach ($p['etiquetas'] as $etiqueta)<li>{{ $etiqueta }}</li>@endforeach
                    </ul>
                @endif
            </div>
        </li>
    @endforeach
    </ul>
    <p class="d-aparte"><a class="d-btn d-btn-linea" href="{{ $menu_url }}">Ver menú</a></p>
</div></section>
@endif

@if ($hayReserva)
<section id="reservar" class="d-seccion d-banda" aria-labelledby="d-reserva"><div class="d-wrap d-reserva">
    <div>
        <span class="d-regla" aria-hidden="true"></span>
        <h2 id="d-reserva">Reservación</h2>
        @unless ($reservas_activas)<p class="d-desc">Por ahora no recibimos reservas en línea.</p>@endunless
    </div>
    <div class="d-caja">@include('sitio.partes.reservar')</div>
</div></section>
@endif

@if ($hayHorario || $hayContacto)
<section class="d-seccion" aria-labelledby="d-visita"><div class="d-wrap">
    <span class="d-regla" aria-hidden="true"></span>
    <h2 id="d-visita">Horario y contacto</h2>
    <div class="d-dos">
        @if ($hayHorario)
            <div id="horario" class="d-bloque"><h3>Horario</h3>@include('sitio.partes.horarios')</div>
        @endif
        @if ($hayContacto)
            <div id="contacto" class="d-bloque"><h3>Contacto</h3>@include('sitio.partes.contacto')</div>
        @endif
    </div>
</div></section>
@endif
</main>

<footer class="d-pie"><div class="d-wrap d-pie-fila">
    <span class="d-logo">{{ $nombre }}</span>
    <span>Sitio creado con {{ config('app.name') }}</span>
</div></footer>
</div>
</x-sitio.layout>
