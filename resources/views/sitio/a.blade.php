<x-sitio.layout :r="$r" plantilla="a" :descripcion="$r->descripcion">
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
<section class="hero"><div class="wrap">
    @if ($direccion)<p class="eyebrow">{{ $direccion }}</p>@endif
    <h1>{{ $r->eslogan ?: $r->nombre }}</h1>
    <div class="lead">
        @if ($r->eslogan)<p>{{ $r->nombre }}</p>@endif
        <a class="btn" href="#reservar">Reservar mesa</a>
    </div></div>
    <div class="ph hero-ph"><x-sitio.imagen :ruta="$hero" :alt="'Portada de '.$r->nombre" :ancho="1600" :alto="900" sizes="100vw" :prioridad="true" /></div>
</section>

@if ($r->descripcion)
<section id="nosotros" class="sec nosotros"><div class="wrap"><div class="rejilla">
    <p class="eyebrow">01 — Nosotros</p>
    <p class="texto-grande">{{ $r->descripcion }}</p>
</div></div></section>
@endif

@if ($destacados)
<section id="menu" class="sec menu"><div class="wrap">
    <p class="eyebrow">02 — Menú destacado</p><h2>De temporada.</h2>
    @foreach ($destacados as $p)
        <article @class(['plato', 'agotado' => $p['agotado']])>
            <div class="ph plato-ph"><x-sitio.imagen :ruta="$p['imagen']" :alt="$p['nombre']" :ancho="480" :alto="600" sizes="96px" /></div>
            <h3>{{ $p['nombre'] }}@if ($p['agotado']) <span class="etiqueta">Agotado</span>@endif</h3>
            <span class="precio">{{ $p['precio'] }}</span>
            @if ($p['descripcion'])<p>{{ $p['descripcion'] }}</p>@endif
        </article>
    @endforeach
    <p class="ver-todo"><a class="btn btn-alt" href="{{ $menu_url }}">Ver menú completo</a></p>
</div></section>
@endif

@if ($galeria)
<section id="galeria" class="sec galeria"><div class="wrap"><p class="eyebrow">03 — Galería</p><h2>La mesa.</h2>
    <div class="teselas">@foreach ($galeria as $foto)<div class="ph"><x-sitio.imagen :ruta="$foto" :alt="'Foto '.$loop->iteration.' de '.$r->nombre" /></div>@endforeach</div>
</div></section>
@endif

<section id="reservar" class="sec reservar"><div class="wrap"><div class="rejilla">
    <div><p class="eyebrow">04 — Reservaciones</p><h2>Tu mesa, en un minuto.</h2>
    @unless ($reservas_activas)<p>Por ahora no recibimos reservas en línea.</p>@endunless</div>
    <div>@include('sitio.partes.reservar')</div>
</div></div></section>

<section id="ubicacion" class="sec ubicacion"><div class="wrap"><p class="eyebrow">05 — Ubicación y horario</p><h2>Visítanos.</h2>
    <div class="rejilla">
        <div class="mapa" role="img" aria-label="Ubicación de {{ $r->nombre }}"><span class="pin"></span><span class="mapa-nombre">{{ $r->nombre }}</span></div>
        <div class="info">@include('sitio.partes.contacto')@include('sitio.partes.horarios')</div>
    </div>
</div></section>
</main>

<footer class="pie"><div class="wrap"><span class="logo">{{ $r->nombre }}</span><span>© {{ now()->year }} {{ $r->nombre }}</span></div></footer>
</x-sitio.layout>
