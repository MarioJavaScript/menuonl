@php
    /*
     * Plantilla E · Cafetería. Todo sale de los datos del restaurante; una sección sin datos se omite.
     * Las palabras grandes de la portada se derivan del nombre (la última palabra a la derecha, el resto a la izquierda)
     * y de un eslogan corto; no hay campos propios.
     */
    $nombre = trim((string) $r->nombre);
    $palabraIzquierda = $nombre;
    $palabraDerecha = null;
    if (preg_match('/\A(.*\S)\s+(\S+)\z/su', $nombre, $partes) === 1) {
        [, $palabraIzquierda, $palabraDerecha] = $partes;
    }
    $eslogan = trim((string) $r->eslogan);
    $acentoPortada = $eslogan !== '' && mb_strlen($eslogan) <= 24 ? $eslogan : null;

    $d = compact('r', 'menu_url', 'horarios', 'direccion', 'telefono', 'telefono_enlace', 'whatsapp_digitos', 'instagram_url', 'facebook_url', 'mapa_url');
@endphp
<x-sitio.cafeteria.layout :r="$r" :titulo="$nombre.($eslogan !== '' ? ' · '.$eslogan : '')" :descripcion="$r->descripcion" pagina="inicio">
<div class="cal-portada">
    <x-sitio.cafeteria.cabecera :d="$d" actual="inicio" />

    <main id="principal" class="cal-portada__cuerpo" tabindex="-1">
        <h1 class="cal-visualmente-oculto">{{ $nombre }}@if ($eslogan !== ''): {{ $eslogan }}@endif</h1>
        <div class="cal-portada__escena">
            <p class="cal-portada__palabra cal-portada__palabra--izq" style="--cal-n: {{ mb_strlen($palabraIzquierda) }}" aria-hidden="true">{{ $palabraIzquierda }}</p>
            <div class="cal-portada__bloque">
                <x-sitio.imagen :ruta="$hero" :alt="'Portada de '.$nombre" :ancho="800" :alto="1000" sizes="(min-width:768px) 25vw, 62vw" :prioridad="true" class="cal-portada__foto" />
            </div>
            <div class="cal-portada__derecha">
                @if ($palabraDerecha !== null)
                    <p class="cal-portada__palabra" style="--cal-n: {{ mb_strlen($palabraDerecha) }}" aria-hidden="true">{{ $palabraDerecha }}</p>
                @endif
                @if ($acentoPortada !== null)
                    <p class="cal-portada__acento" aria-hidden="true">{{ $acentoPortada }}</p>
                @endif
            </div>
        </div>
        <div class="cal-portada__pie">
            @if ($eslogan !== '')<p class="cal-portada__slogan">{{ $eslogan }}</p>@endif
            <a class="cal-boton cal-boton--claro" href="{{ $menu_url }}">Ver el menú</a>
        </div>
    </main>
</div>

<x-sitio.cafeteria.contacto :d="$d" />
<x-sitio.cafeteria.pie :d="$d" />
</x-sitio.cafeteria.layout>
