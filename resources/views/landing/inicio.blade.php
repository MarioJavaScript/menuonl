@extends('landing.layout')

@section('titulo', config('app.name').' | Menú digital con QR y NFC para restaurantes')

@section('meta')
@php
    $base = rtrim((string) config('app.url'), '/');
    $descripcion = 'Tus clientes escanean un QR o tocan la mesa con su celular y se abre tu menú, sin descargar nada.';
@endphp
<meta name="description" content="{{ $descripcion }}">
<link rel="canonical" href="{{ $base }}/">
<meta property="og:type" content="website">
<meta property="og:locale" content="es_MX">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ config('app.name') }}: tu menú, en la mesa de tus clientes">
<meta property="og:description" content="Menú digital con QR y NFC para restaurantes. Tus clientes no descargan nada.">
<meta property="og:url" content="{{ $base }}/">
<meta property="og:image" content="{{ $base }}/brand/og-image-1200x630.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => config('app.name'), 'url' => $base.'/', 'logo' => $base.'/brand/icon-512.png'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endsection

@section('contenido')
<main id="contenido">

  <!-- 1. Hero -->
  <section class="hero" aria-labelledby="titulo-hero">
    <div class="constellation" aria-hidden="true">
      @include('landing.partials.red-hero')
    </div>
    <div class="wrap hero__grid">
      <div class="hero__copy">
        <h1 id="titulo-hero">Tu menú, en la mesa de tus clientes</h1>
        <p class="lead" data-enter="1">Tus clientes escanean un QR o tocan la mesa con su celular y el menú se abre en su navegador, sin descargar nada.</p>
        <div class="actions" data-enter="3">
          @if ($url = $soporte->enlaceWhatsapp('Hola, quiero conocer '.config('app.name').' para mi restaurante.'))
<a class="btn btn--primary" href="{{ $url }}" rel="noopener noreferrer">
            <svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>
</svg>
            <span>Escríbenos por WhatsApp</span>
          </a>
@endif
          @if ($demoUrl)
<a class="btn btn--secondary" href="{{ $demoUrl }}">
            <span>Ver un menú de ejemplo</span>
          </a>
@endif
        </div>
        <ul class="ticks" data-enter="4">
          <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Tus clientes no descargan nada</li>
          <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Con el plan Completo, tú mismo editas tu menú</li>
        </ul>
      </div>

      <!-- Componente: celular con captura. Para poner una captura real:
           cambia src/art/captura-menu.svg por captura-menu.png (9:19) y borra el <span class="phone__tag">. -->
      <div class="stage" data-enter="2">
        <img class="slashi slashi--hero" src="{{ asset('landing/img/slashi-chef-color-240.webp') }}"
          srcset="{{ asset('landing/img/slashi-chef-color-240.webp') }} 240w, {{ asset('landing/img/slashi-chef-color-480.webp') }} 480w"
          sizes="150px" width="240" height="122" alt="Slashi, la mascota de {{ config('app.name') }}, con gorro de chef.">
        <div class="stage__device">
        <figure class="phone">
          <div class="phone__screen">
            <picture>
              <source type="image/webp"
                srcset="{{ asset('landing/img/captura-menu-360.webp') }} 360w, {{ asset('landing/img/captura-menu-540.webp') }} 540w, {{ asset('landing/img/captura-menu-720.webp') }} 720w"
                sizes="(min-width: 56rem) 300px, 76vw">
              <img src="{{ asset('landing/img/captura-menu-540.webp') }}" width="540" height="1140"
                alt="Ejemplo de un menú digital abierto en un celular, con categorías y platillos.">
            </picture>
          </div>
          <span class="phone__tag mark">[CAPTURA DEL MENÚ]</span>
        </figure>
        <div class="tent" aria-hidden="true">
          <span class="tent__icon"><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z"/>
  <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z"/>
</svg></span>
          <span class="tent__text"><strong>Mesa 4</strong>Escanea o toca</span>
        </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 2. Cómo funciona -->
  <section class="section section--ice" id="como-funciona" aria-labelledby="titulo-pasos">
    <div class="wrap">
      <h2 id="titulo-pasos" data-reveal>Así funciona</h2>
      <ol class="steps">
        <li class="step" data-reveal>
          <h3>Arma tu menú</h3>
          <p>Con el plan Completo escribes tus platillos y precios en tu panel. En los otros planes los cambios se piden por WhatsApp. <span class="mark">[CONFIRMAR]</span> Cómo se entrega el primer menú.</p>
        </li>
        <li class="step" data-reveal>
          <h3>Pon el QR o el NFC en cada mesa</h3>
          <p>Cada mesa lleva el suyo, y así sabes desde cuál escanearon.</p>
        </li>
        <li class="step" data-reveal>
          <h3>Tus clientes abren el menú</h3>
          <p>Escanean el QR o tocan con el celular. Se abre en su navegador, sin descargar nada.</p>
        </li>
      </ol>
      <p class="after-steps" data-reveal>Con el plan Completo, cambias un precio en tu panel y el menú de tus clientes se actualiza.</p>
    </div>
  </section>

  <!-- 3. Qué incluye -->
  <section class="section" id="incluye" aria-labelledby="titulo-incluye">
    <div class="wrap split">
      <div class="split__head" data-reveal>
        <h2 id="titulo-incluye">Lo que puedes hacer con {{ config('app.name') }}</h2>
        <p>Algunas cosas dependen de tu plan. Mira los planes más abajo.</p>
      </div>
      <ul class="features" data-reveal>
        <li>
          <span class="feature__icon"><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
</svg></span>
          <div><h3>Edita tu menú tú mismo</h3><p>En el plan Completo cambias platillos y precios desde tu panel, cuando quieras.</p></div>
        </li>
        <li>
          <span class="feature__icon"><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z"/>
  <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z"/>
</svg></span>
          <div><h3>QR o NFC en la mesa</h3><p>Tus clientes escanean o tocan. No descargan ninguna app.</p></div>
        </li>
        <li>
          <span class="feature__icon"><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z"/>
</svg></span>
          <div><h3>Reservas</h3><p>Tus clientes reservan desde tu sitio. Está en los planes Completo y Sitio y menú.</p></div>
        </li>
        <li>
          <span class="feature__icon"><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
</svg></span>
          <div><h3>Escaneos por mesa</h3><p>Mira cuántas veces escanearon o tocaron el QR o NFC de cada mesa, y cuántas reservas tienes. No son visitas. <span class="mark">[CONFIRMAR]</span> Plan y pantalla donde se verá.</p></div>
        </li>
        <li>
          <span class="feature__icon"><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>
</svg></span>
          <div><h3>Pedidos por WhatsApp</h3><p>Los pedidos salen por WhatsApp. Por ahora no hay pago en línea.</p></div>
        </li>
      </ul>
    </div>
  </section>

  <!-- 4. Planes -->
  <section class="section section--ice" id="planes" aria-labelledby="titulo-planes">
    <div class="wrap">
      <div class="section__head" data-reveal>
        <h2 id="titulo-planes">Elige tu plan</h2>
        <p><span class="mark">[CONFIRMAR]</span> Los nombres y precios finales siguen pendientes.</p>
      </div>
      <div class="plans">
        <article class="plan" data-reveal aria-labelledby="plan-completo">
          <h3 id="plan-completo">Completo</h3>
          <ul class="plan__list">
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Sitio y menú públicos</li>
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Reservas</li>
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Panel para editar tu menú</li>
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Métricas de escaneos por mesa y de reservas <span class="mark">[CONFIRMAR]</span></li>
          </ul>
          <p class="plan__price"><span class="mark">[PRECIO]</span></p>
          @if ($url = $soporte->enlaceWhatsapp('Hola, quiero informes del plan Completo de '.config('app.name').'.'))
<a class="btn btn--secondary btn--block" href="{{ $url }}" rel="noopener noreferrer">
            <span>Pregunta por este plan<span class="sr-only">: Completo</span></span>
          </a>
@endif
        </article>
        <article class="plan" data-reveal aria-labelledby="plan-sitio">
          <h3 id="plan-sitio">Sitio y menú</h3>
          <ul class="plan__list">
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Sitio y menú públicos</li>
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Reservas</li>
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Desde tu panel atiendes las reservas y editas el horario, los teléfonos y el WhatsApp de pedidos</li>
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Para cambiar tu menú, escríbenos por WhatsApp</li>
          </ul>
          <p class="plan__price"><span class="mark">[PRECIO]</span></p>
          @if ($url = $soporte->enlaceWhatsapp('Hola, quiero informes del plan Sitio y menú de '.config('app.name').'.'))
<a class="btn btn--secondary btn--block" href="{{ $url }}" rel="noopener noreferrer">
            <span>Pregunta por este plan<span class="sr-only">: Sitio y menú</span></span>
          </a>
@endif
        </article>
        <article class="plan" data-reveal aria-labelledby="plan-menu">
          <h3 id="plan-menu">Solo menú</h3>
          <ul class="plan__list">
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Menú digital</li>
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>QR o NFC para tus mesas</li>
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>No incluye panel: los cambios se piden por WhatsApp</li>
          </ul>
          <p class="plan__price"><span class="mark">[PRECIO]</span></p>
          @if ($url = $soporte->enlaceWhatsapp('Hola, quiero informes del plan Solo menú de '.config('app.name').'.'))
<a class="btn btn--secondary btn--block" href="{{ $url }}" rel="noopener noreferrer">
            <span>Pregunta por este plan<span class="sr-only">: Solo menú</span></span>
          </a>
@endif
        </article>
      </div>
    </div>
  </section>

  <!-- 5. Panel y testimonio (marcadores hasta tener material real) -->
  <section class="section" id="panel" aria-labelledby="titulo-panel">
    <div class="wrap split">
      <div class="split__head" data-reveal>
        <h2 id="titulo-panel">Mira cómo lo editas</h2>
        <p>Con el plan Completo cambias tu menú desde tu panel. Aquí irá una captura real.</p>
      </div>
      <div class="proof" data-reveal>
        <div class="slot" role="img" aria-label="Espacio reservado para una captura del panel, todavía pendiente.">
          <span class="mark">[CAPTURA DEL PANEL]</span>
        </div>
        <figure class="quote">
          <blockquote><p><span class="mark">[TESTIMONIO]</span></p></blockquote>
          <figcaption><span class="mark">[NOMBRE Y RESTAURANTE]</span></figcaption>
        </figure>
      </div>
    </div>
  </section>

  <!-- 6. Preguntas frecuentes -->
  <section class="section section--ice" id="preguntas" aria-labelledby="titulo-preguntas">
    <div class="wrap faq-wrap">
      <h2 id="titulo-preguntas" data-reveal>Preguntas frecuentes</h2>
      <div class="faq" data-reveal>
        <details>
          <summary><span>¿Mis clientes tienen que descargar algo?</span><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
</svg></summary>
          <p>No. Escanean el QR o tocan la mesa con su celular y el menú se abre en el navegador. Para tocar la mesa, el celular debe tener NFC.</p>
        </details>
        <details>
          <summary><span>¿Yo puedo cambiar mi menú?</span><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
</svg></summary>
          <p>Depende del plan. En el plan Completo, sí: lo editas tú desde tu panel. En Sitio y menú editas el horario, los teléfonos y el WhatsApp de pedidos, y para cambiar el menú nos escribes por WhatsApp. En Solo menú no hay panel: los cambios se piden por WhatsApp.</p>
        </details>
        <details>
          <summary><span>¿Qué incluye cada plan?</span><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
</svg></summary>
          <p><strong>Completo:</strong> sitio y menú públicos, reservas y panel para editar tu menú. <span class="mark">[CONFIRMAR]</span> Métricas de escaneos.</p>
          <p><strong>Sitio y menú:</strong> sitio y menú públicos con reservas. Desde tu panel atiendes las reservas y editas el horario, los teléfonos y el WhatsApp de pedidos.</p>
          <p><strong>Solo menú:</strong> menú digital con QR o NFC, sin panel. Los cambios se piden por WhatsApp.</p>
        </details>
        <details>
          <summary><span>¿Mis clientes pueden pedir y pagar en línea?</span><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
</svg></summary>
          <p>Los pedidos salen por WhatsApp. Por ahora no hay pago en línea.</p>
        </details>
        <details>
          <summary><span>¿Qué puedo medir?</span><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
</svg></summary>
          <p>Se registran los escaneos de QR o NFC por mesa y las reservas. No medimos visitas. <span class="mark">[CONFIRMAR]</span> Plan y pantalla donde se verán las métricas.</p>
        </details>
        <details>
          <summary><span>¿Cuánto cuesta y hay contrato?</span><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
</svg></summary>
          <p><span class="mark">[CONFIRMAR]</span> Precio de cada plan y condiciones del contrato.</p>
        </details>
        <details>
          <summary><span>¿Cuánto tarda en estar listo mi menú y qué soporte tengo?</span><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
</svg></summary>
          <p><span class="mark">[CONFIRMAR]</span> Tiempo de alta y tipo de soporte. Mientras tanto, puedes escribirnos por WhatsApp.</p>
        </details>
      </div>
    </div>
  </section>

  <!-- 7. Cierre -->
  <section class="closing on-dark" aria-labelledby="titulo-cierre">
    <div class="constellation constellation--dark" aria-hidden="true">
      @include('landing.partials.red-cierre')
    </div>
    <div class="wrap closing__grid">
      <div class="closing__copy" data-reveal>
        <h2 id="titulo-cierre">Platiquemos de tu restaurante</h2>
        @if ($soporte->enlaceWhatsapp())
          <p>Escríbenos por WhatsApp y te contamos cómo quedaría tu menú.</p>
        @endif
        <div class="actions">
          @if ($url = $soporte->enlaceWhatsapp('Hola, quiero conocer '.config('app.name').' para mi restaurante.'))
<a class="btn btn--light" href="{{ $url }}" rel="noopener noreferrer">
            <svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>
</svg>
            <span>Escríbenos por WhatsApp</span>
          </a>
@endif
          @if ($demoUrl)
<a class="btn btn--ghost" href="{{ $demoUrl }}">
            <span>Ver un menú de ejemplo</span>
          </a>
@endif
        </div>
      </div>
      <img class="slashi slashi--closing" src="{{ asset('landing/img/slashi-chef-negativo-320.webp') }}"
        srcset="{{ asset('landing/img/slashi-chef-negativo-320.webp') }} 320w, {{ asset('landing/img/slashi-chef-negativo-640.webp') }} 640w"
        sizes="(min-width: 56rem) 280px, 200px" width="320" height="163"
        alt="Slashi, la mascota de {{ config('app.name') }}, con gorro de chef." loading="lazy" decoding="async">
    </div>
  </section>

</main>
@endsection
