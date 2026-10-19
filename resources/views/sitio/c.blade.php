<x-sitio.layout :r="$r" plantilla="c" :descripcion="$r->descripcion">
<div class="pagina">
<div class="mast"><span>Nº 01 · {{ now()->translatedFormat('F Y') }}</span>@if ($direccion)<span>{{ $direccion }}</span>@endif</div>
<header class="cab"><div class="dentro">
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
    <div class="ttl"><div><p class="kicker">{{ $r->eslogan ? $r->nombre : 'Bienvenidos' }}</p><h1>{{ $r->eslogan ?: $r->nombre }}</h1></div>
        <div>@if ($r->descripcion)<p class="resumen">{{ \Illuminate\Support\Str::limit($r->descripcion, 180) }}</p>@endif<a class="btn" href="#reservar">Reservar mesa</a></div></div>
    <div class="ph"><x-sitio.imagen :ruta="$hero" :alt="'Portada de '.$r->nombre" :ancho="1600" :alto="900" sizes="(min-width:900px) 40vw, 100vw" :prioridad="true" /></div>
</section>
<div class="franja">
    <div><b>Horario</b>{{ collect($horarios)->firstWhere('cerrado', false)['texto'] ?? 'Consulta abajo' }}</div>
    <div><b>Cocina</b>{{ $r->eslogan ?: 'De la casa' }}</div>
    <div><b>Reservas</b>{{ $reservas_activas ? 'En línea' : 'Por teléfono' }}</div>
    <div><b>Contacto</b>{{ $telefono ?: ($whatsapp ?: '—') }}</div>
</div>

@if ($r->descripcion)
<section id="nosotros" class="nosotros"><div class="lbl"><p class="kicker">Nosotros</p><h2>La casa</h2></div><div class="cuerpo"><p>{{ $r->descripcion }}</p></div></section>
@endif

@if ($destacados)
<div class="cab-sec" id="menu"><div><p class="kicker">Menú destacado</p><h2>La carta</h2></div><a class="btn" href="{{ $menu_url }}">Menú completo</a></div>
<div class="menu">
    @foreach (collect($destacados)->groupBy('categoria') as $categoria => $platos)
    <section><h3>{{ $categoria !== '' ? $categoria : 'De la casa' }}</h3>
        @foreach ($platos as $p)
            <div @class(['it', 'agotado' => $p['agotado']])>
                <div class="ph"><x-sitio.imagen :ruta="$p['imagen']" :alt="$p['nombre']" :ancho="480" :alto="600" sizes="72px" /></div>
                <div><strong>{{ $p['nombre'] }}</strong>@if ($p['agotado'])<span class="etiqueta">Agotado</span>@endif @if ($p['descripcion'])<small>{{ $p['descripcion'] }}</small>@endif</div>
                <span>{{ $p['precio'] }}</span>
            </div>
        @endforeach
    </section>
    @endforeach
</div>
@endif

@if ($galeria)
<div class="cab-sec" id="galeria"><div><p class="kicker">Galería</p><h2>Instantáneas</h2></div></div>
<div class="gal">@foreach ($galeria as $foto)<figure><div class="ph"><x-sitio.imagen :ruta="$foto" :alt="'Foto '.$loop->iteration.' de '.$r->nombre" /></div><figcaption><span>{{ $r->nombre }}</span><span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span></figcaption></figure>@endforeach</div>
@endif

<section id="reservar" class="res"><div class="lado"><p class="kicker">Reservaciones</p><h2>Aparta tu mesa</h2>
    @unless ($reservas_activas)<p>Por ahora no recibimos reservas en línea.</p>@endunless</div>
    <div class="cuerpo">@include('sitio.partes.reservar')</div></section>

<section id="ubicacion" class="loc">
    <div class="mapa" role="img" aria-label="Ubicación de {{ $r->nombre }}"><i></i><span>{{ $r->nombre }}</span></div>
    <div class="info"><p class="kicker">Ubicación y horario</p><h2>Visítanos</h2>@include('sitio.partes.contacto')@include('sitio.partes.horarios')</div>
</section>
</main>
<footer class="pie"><span class="logo">{{ $r->nombre }}</span><span>© {{ now()->year }} {{ $r->nombre }}</span></footer>
</div>
</x-sitio.layout>
