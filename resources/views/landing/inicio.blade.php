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
</svg>Tú mismo editas tu menú desde tu panel</li>
        </ul>
      </div>

      <!-- Componente: celular con captura. Para poner una captura real, cambia las imágenes captura-menu-*.webp (9:19). -->
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
          <p>Escribes tus platillos, precios y fotos en tu panel.</p>
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
      <p class="after-steps" data-reveal>Cambias un precio en tu panel y el menú de tus clientes se actualiza.</p>
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
          <div><h3>Edita tu menú tú mismo</h3><p>En los dos planes cambias platillos, precios y fotos desde tu panel, cuando quieras.</p></div>
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
          <div><h3>Sitio web y reservas</h3><p>Con Aura tienes tu sitio web con plantilla, y tus clientes reservan desde ahí.</p></div>
        </li>
        <li>
          <span class="feature__icon"><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>
</svg></span>
          <div><h3>Pedidos por WhatsApp</h3><p>Tus clientes arman su pedido en el menú y te llega por WhatsApp, para domicilio o para recoger. Por ahora no hay pago en línea.</p></div>
        </li>
      </ul>
    </div>
  </section>

  <!-- 4. Planes. Los precios salen de config/menuonl.php (precios). -->
  <section class="section section--ice" id="planes" aria-labelledby="titulo-planes">
    <div class="wrap">
      <div class="section__head" data-reveal>
        <h2 id="titulo-planes">Elige tu plan</h2>
        <p>Los dos planes incluyen tu panel para editar tu menú y los pedidos por WhatsApp.</p>
      </div>
      <div class="plans">
        <article class="plan" data-reveal aria-labelledby="plan-lite">
          <h3 id="plan-lite">Lite</h3>
          <ul class="plan__list">
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Menú digital con QR o NFC para tus mesas</li>
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Pedidos por WhatsApp, para domicilio o para recoger</li>
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Desde tu panel editas tu menú, tu horario y tus datos</li>
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>No incluye sitio web: tu dirección abre directo el menú</li>
          </ul>
          <p class="plan__price">${{ number_format((int) config('menuonl.precios.lite')) }} al mes</p>
          @if ($url = $soporte->enlaceWhatsapp('Hola, quiero informes del plan Lite de '.config('app.name').'.'))
<a class="btn btn--secondary btn--block" href="{{ $url }}" rel="noopener noreferrer">
            <span>Pregunta por este plan<span class="sr-only">: Lite</span></span>
          </a>
@endif
        </article>
        <article class="plan" data-reveal aria-labelledby="plan-aura">
          <h3 id="plan-aura">Aura</h3>
          <ul class="plan__list">
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Sitio web con plantilla y menú digital con QR o NFC</li>
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Reservas en línea desde tu sitio</li>
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Pedidos por WhatsApp, para domicilio o para recoger</li>
            <li><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
</svg>Desde tu panel editas tu sitio y tu menú</li>
          </ul>
          <p class="plan__price">${{ number_format((int) config('menuonl.precios.aura')) }} al mes</p>
          @if ($url = $soporte->enlaceWhatsapp('Hola, quiero informes del plan Aura de '.config('app.name').'.'))
<a class="btn btn--secondary btn--block" href="{{ $url }}" rel="noopener noreferrer">
            <span>Pregunta por este plan<span class="sr-only">: Aura</span></span>
          </a>
@endif
        </article>
      </div>
      @if (filled(config('menuonl.precios.nota')))
        <p class="plans__nota" data-reveal>{{ config('menuonl.precios.nota') }}</p>
      @endif
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
          <p>Sí, en los dos planes. Desde tu panel cambias tus platillos, precios, fotos y horario cuando quieras. Con Aura también editas tu sitio.</p>
        </details>
        <details>
          <summary><span>¿Qué incluye cada plan?</span><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
</svg></summary>
          <p><strong>Lite:</strong> menú digital con QR o NFC y pedidos por WhatsApp, para domicilio o para recoger. Editas tu menú desde tu panel. No incluye sitio web.</p>
          <p><strong>Aura:</strong> todo lo de Lite, más tu sitio web con plantilla y reservas en línea. Editas tu sitio y tu menú desde tu panel.</p>
        </details>
        <details>
          <summary><span>¿Mis clientes pueden pedir y pagar en línea?</span><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
</svg></summary>
          <p>Pueden pedir: arman su pedido en el menú y lo mandan por WhatsApp, para domicilio o para recoger. Por ahora no hay pago en línea.</p>
        </details>
        <details>
          <summary><span>¿Cuánto cuesta y hay contrato?</span><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
</svg></summary>
          <p>Lite cuesta ${{ number_format((int) config('menuonl.precios.lite')) }} al mes y Aura ${{ number_format((int) config('menuonl.precios.aura')) }} al mes. {{ config('menuonl.precios.nota') }} Sin contrato: cancelas cuando quieras.</p>
        </details>
        <details>
          <summary><span>¿Cuánto tarda en estar listo mi menú y qué soporte tengo?</span><svg class="icon" focusable="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
  <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
</svg></summary>
          <p>Nosotros cargamos tu primer menú. Queda listo en 48 horas. Te atendemos por WhatsApp en horario de oficina.</p>
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
