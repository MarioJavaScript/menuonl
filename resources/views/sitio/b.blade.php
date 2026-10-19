<x-sitio.layout :r="$r" plantilla="b" :descripcion="$r->descripcion">
<header class="cab"><div class="wrap">
    <a class="logo" href="#top">{{ $r->nombre }}</a>
    <nav aria-label="Principal">
        @if ($r->descripcion)<a href="#nosotros">Nosotros</a>@endif
        @if ($destacados)<a href="#menu">Menú</a>@endif
        @if ($galeria)<a href="#galeria">Galería</a>@endif
        <a href="#reservar">Reservaciones</a>
        <a href="#ubicacion">Ubicación</a>
    </nav>
    <a class="btn btn-chico" href="#reservar">Reservar</a>
</div></header>

<main id="top">
<section class="hero">
    <div class="fondo"><x-sitio.imagen :ruta="$hero" :alt="'Portada de '.$r->nombre" :ancho="1600" :alto="900" sizes="100vw" :prioridad="true" /></div>
    <div class="wrap">
        @if ($direccion)<p class="eyebrow">{{ $direccion }}</p>@endif
        <h1>{{ $r->eslogan ?: $r->nombre }}</h1>
        @if ($r->eslogan)<p>{{ $r->nombre }}</p>@endif
        <div class="fila"><a class="btn" href="#reservar">Reservar mesa</a>@if ($destacados)<a class="btn btn-linea" href="#menu">Ver menú</a>@endif</div>
    </div>
</section>

@if ($r->descripcion)
<section id="nosotros" class="s nosotros"><div class="wrap"><p class="eyebrow">Nosotros</p><h2>Una mesa, una historia.</h2>
    <blockquote>{{ $r->descripcion }}</blockquote></div></section>
@endif

@if ($destacados)
<section id="menu" class="s"><div class="wrap"><p class="eyebrow">Menú destacado</p><h2>Lo que pedirás dos veces.</h2>
    <div class="platos">
    @foreach ($destacados as $p)
        <article @class(['plato', 'agotado' => $p['agotado']])>
            <div class="ph"><x-sitio.imagen :ruta="$p['imagen']" :alt="$p['nombre']" :ancho="480" :alto="600" sizes="(min-width:900px) 140px, 96px" /></div>
            <div><h3>{{ $p['nombre'] }}</h3>@if ($p['descripcion'])<p>{{ $p['descripcion'] }}</p>@endif
            <span class="precio">{{ $p['precio'] }}</span>@if ($p['agotado']) <span class="etiqueta">Agotado</span>@endif</div>
        </article>
    @endforeach
    </div>
    <p class="ver-todo"><a class="btn btn-linea" href="{{ $menu_url }}">Ver menú completo</a></p>
</div></section>
@endif

@if ($galeria)
<section id="galeria" class="s"><div class="wrap"><p class="eyebrow">Galería</p><h2>Luz, vajilla, fuego.</h2>
    <div class="mosaico">@foreach ($galeria as $foto)<div @class(['ph', 'grande' => $loop->first && $loop->count > 3])><x-sitio.imagen :ruta="$foto" :alt="'Foto '.$loop->iteration.' de '.$r->nombre" /></div>@endforeach</div>
</div></section>
@endif

<section id="reservar" class="s reservar"><div class="wrap"><div class="caja">
    <div class="txt"><p class="eyebrow">Reservaciones</p><h2>Reserva tu mesa.</h2>
    @unless ($reservas_activas)<p class="suave">Por ahora no recibimos reservas en línea.</p>@endunless</div>
    <div class="cuerpo">@include('sitio.partes.reservar')</div>
</div></div></section>

<section id="ubicacion" class="s ubicacion"><div class="wrap"><p class="eyebrow">Ubicación y horario</p><h2>Te esperamos.</h2>
    <div class="cols">
        <div class="mapa" role="img" aria-label="Ubicación de {{ $r->nombre }}"><i></i><span>{{ $r->nombre }}</span></div>
        <div class="info">@include('sitio.partes.contacto')@include('sitio.partes.horarios')</div>
    </div>
</div></section>
</main>

<footer class="pie"><div class="wrap"><span class="logo">{{ $r->nombre }}</span><span>© {{ now()->year }} {{ $r->nombre }}</span></div></footer>
</x-sitio.layout>
