@extends('landing.layout')

@php
    $base = rtrim((string) config('app.url'), '/');
    $conNfc = (bool) config('menuonl.nfc_disponible');
    $precioLite = '$'.number_format((int) config('menuonl.precios.lite')).' al mes';
    $precioAura = '$'.number_format((int) config('menuonl.precios.aura')).' al mes';
    $mensajeConocer = 'Hola, quiero conocer '.config('app.name').' para mi restaurante.';
    $codigosEnMesa = $conNfc ? 'QR o NFC' : 'QR';
    $descripcion = $conNfc
        ? 'Tus clientes escanean un QR o tocan la mesa con su celular y se abre tu menú, sin descargar nada. Tú lo editas y recibes pedidos por WhatsApp.'
        : 'Tus clientes escanean un QR y se abre tu menú, sin descargar nada. Tú lo editas y recibes pedidos por WhatsApp.';
@endphp

@section('titulo', config('app.name').' | Menú digital con '.($conNfc ? 'QR y NFC' : 'QR').' para restaurantes')

@section('meta')
<meta name="description" content="{{ $descripcion }}">
<link rel="canonical" href="{{ $base }}/">
<meta property="og:type" content="website">
<meta property="og:locale" content="es_MX">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ config('app.name') }}: tu menú, a un escaneo">
<meta property="og:description" content="{{ $descripcion }}">
<meta property="og:url" content="{{ $base }}/">
<meta property="og:image" content="{{ $base }}/brand/og-image-1200x630.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => config('app.name'), 'url' => $base.'/', 'logo' => $base.'/brand/icon-512.png'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
<link rel="preload" href="{{ asset('fonts/landing/space-grotesk-latin-var.woff2') }}" as="font" type="font/woff2" crossorigin>
<script src="{{ asset('js/landing-criterio.js').'?v='.filemtime(public_path('js/landing-criterio.js')) }}"></script>
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
@vite('resources/js/landing.js')
@endif
@endsection

@section('contenido')
<main id="contenido">

  <div class="escena" data-escena>
    <div class="escena__vista" data-escena-vista>
      <canvas class="escena__lienzo" aria-hidden="true"
        data-imagen-logo="{{ asset('landing/img/menubly-negativo-306.webp') }}"
        data-imagen-simbolo="{{ asset('landing/img/menubly-simbolo-128.webp') }}"
        data-texto-soporte="{{ $conNfc ? 'o acerca tu celular a la etiqueta' : 'Se abre sin descargar nada' }}"
        @if ($conNfc) data-anillos @endif></canvas>
      <div class="escena__progreso" aria-hidden="true"><i data-escena-barra></i></div>

      <!-- 1. Hero -->
      <section class="tramo tramo--hero" aria-labelledby="titulo-hero">
        <div class="wrap tramo__interior">
          <div class="tramo__caja">
            <p class="pill">Menú digital para restaurantes</p>
            <h1 id="titulo-hero">Tu menú,<br>a un <span class="hl">escaneo</span>.</h1>
            <p class="sub">
              @if ($conNfc)
                Tus clientes escanean el QR o acercan el celular a la etiqueta NFC, y el menú se abre en su navegador. No tienen que bajar ninguna app.
              @else
                Tus clientes escanean el QR y el menú se abre en su navegador. No tienen que bajar ninguna app.
              @endif
            </p>
            <div class="actions">
              @if ($url = $soporte->enlaceWhatsapp($mensajeConocer))
                <a class="btn btn--primary" href="{{ $url }}" rel="noopener noreferrer">@include('landing.partials.icono', ['nombre' => 'whatsapp'])<span>Quiero {{ config('app.name') }}</span></a>
              @else
                <a class="btn btn--primary" href="#planes"><span>Quiero {{ config('app.name') }}</span></a>
              @endif
              <a class="btn btn--secondary" href="#como-funciona"><span>Ver cómo funciona</span></a>
            </div>
            @if ($demoUrl)
              <a class="enlace-demo" href="{{ $demoUrl }}">Ver un menú de ejemplo</a>
            @endif
            <p class="indicador solo-3d" aria-hidden="true">Desliza</p>
          </div>
          <div class="stage solo-estatica">
            <img class="slashi slashi--hero" src="{{ asset('landing/img/slashi-chef-color-240.webp') }}"
              srcset="{{ asset('landing/img/slashi-chef-color-240.webp') }} 240w, {{ asset('landing/img/slashi-chef-color-480.webp') }} 480w"
              sizes="150px" width="240" height="122" alt="Slashi, la mascota de {{ config('app.name') }}, con gorro de chef." decoding="async">
            <div class="stage__device">
              <figure class="phone">
                <div class="phone__screen">
                  <picture>
                    <source type="image/webp"
                      srcset="{{ asset('landing/img/captura-menu-360.webp') }} 360w, {{ asset('landing/img/captura-menu-540.webp') }} 540w, {{ asset('landing/img/captura-menu-720.webp') }} 720w"
                      sizes="(min-width: 56rem) 300px, 76vw">
                    <img src="{{ asset('landing/img/captura-menu-540.webp') }}" width="540" height="1140" decoding="async"
                      alt="Ejemplo de un menú digital abierto en un celular, con categorías y platillos.">
                  </picture>
                </div>
              </figure>
            </div>
          </div>
        </div>
      </section>

      <!-- 2. Cómo funciona -->
      <section class="tramo tramo--hielo" id="como-funciona" aria-labelledby="titulo-pasos">
        <div class="wrap tramo__interior">
          <div class="tramo__caja">
            <p class="pill">01 · Escanea</p>
            <h2 id="titulo-pasos">Apunta la cámara. <br><span class="hl">Listo.</span></h2>
            <ol class="steps">
              <li class="step"><h3>Arma tu menú</h3><p>Escribes tus platillos, precios y fotos en tu panel.</p></li>
              <li class="step"><h3>Pon tu {{ $codigosEnMesa }} en cada mesa</h3><p>Cada mesa lleva el suyo, y así sabes desde cuál escanearon.</p></li>
              <li class="step"><h3>Tus clientes abren el menú</h3><p>Se abre en su navegador, sin descargar nada.</p></li>
            </ol>
          </div>
        </div>
      </section>

      <!-- 3. Antójate -->
      <section class="tramo tramo--derecha" id="antojate" aria-labelledby="titulo-antojate">
        <div class="wrap tramo__interior">
          <div class="tramo__caja">
            <p class="pill">02 · Antójate</p>
            <h2 id="titulo-antojate">Platillos que <br><span class="hl">se salen</span> de la pantalla.</h2>
            <p class="sub">Cada platillo con su foto, su descripción y su precio.</p>
          </div>
        </div>
      </section>

      <!-- 4. Qué incluye -->
      <section class="tramo tramo--hielo" id="incluye" aria-labelledby="titulo-incluye">
        <div class="wrap tramo__interior">
          <div class="tramo__caja">
            <p class="pill">03 · Beneficios</p>
            <h2 id="titulo-incluye">Todo tu menú, <span class="hl">sin papel</span>.</h2>
            <ul class="features">
              <li>
                <span class="feature__icon">@include('landing.partials.icono', ['nombre' => 'editar'])</span>
                <div><h3>Edita tu menú tú mismo</h3><p>Cambias un precio en tu panel y el menú de tus clientes se actualiza.</p></div>
              </li>
              <li>
                <span class="feature__icon">@include('landing.partials.icono', ['nombre' => 'qr'])</span>
                <div>
                  <h3>{{ $conNfc ? 'QR + NFC' : 'Código QR' }} en la mesa</h3>
                  <p>{{ $conNfc ? 'Escanean el código o acercan el celular a la etiqueta.' : 'Escanean el código con su celular.' }} No descargan ninguna app.</p>
                </div>
              </li>
              <li>
                <span class="feature__icon">@include('landing.partials.icono', ['nombre' => 'foto'])</span>
                <div><h3>Fotos que venden</h3><p>Cada platillo con su foto, en los dos planes.</p></div>
              </li>
              <li>
                <span class="feature__icon">@include('landing.partials.icono', ['nombre' => 'whatsapp'])</span>
                <div><h3>Pedidos por WhatsApp</h3><p>Te llegan listos, para domicilio o para recoger. Por ahora no hay pago en línea.</p></div>
              </li>
            </ul>
          </div>
        </div>
      </section>

      <!-- 5. Planes. Los precios salen de config/menuonl.php (precios). -->
      <section class="tramo" id="planes" aria-labelledby="titulo-planes">
        <div class="wrap tramo__interior">
          <div class="tramo__caja">
            <div class="planes__titulo">
              <img class="slashi slashi--planes" src="{{ asset('landing/img/slashi-chef-color-240.webp') }}"
                srcset="{{ asset('landing/img/slashi-chef-color-240.webp') }} 240w, {{ asset('landing/img/slashi-chef-color-480.webp') }} 480w"
                sizes="110px" width="240" height="122" alt="" loading="lazy" decoding="async">
              <h2 id="titulo-planes">Elige tu <span class="hl">plan</span>.</h2>
            </div>
            <div class="plans">
              <article class="plan" aria-labelledby="plan-lite">
                <h3 id="plan-lite">Lite</h3>
                <p class="plan__price">{{ $precioLite }}</p>
                <ul class="plan__list">
                  <li>@include('landing.partials.icono', ['nombre' => 'check'])Menú digital con {{ $codigosEnMesa }} que tú editas</li>
                  <li>@include('landing.partials.icono', ['nombre' => 'check'])Foto de cada platillo</li>
                  <li>@include('landing.partials.icono', ['nombre' => 'check'])Pedidos por WhatsApp: Domicilio o Para recoger</li>
                  <li>@include('landing.partials.icono', ['nombre' => 'check'])No incluye sitio web: tu dirección abre directo el menú</li>
                </ul>
                @if ($url = $soporte->enlaceWhatsapp('Hola, quiero informes del plan Lite de '.config('app.name').'.'))
                  <a class="btn btn--secondary btn--block" href="{{ $url }}" rel="noopener noreferrer"><span>Pregunta por este plan<span class="sr-only">: Lite</span></span></a>
                @endif
              </article>
              <article class="plan plan--aura" aria-labelledby="plan-aura">
                <h3 id="plan-aura">Aura</h3>
                <p class="plan__price">{{ $precioAura }}</p>
                <ul class="plan__list">
                  <li>@include('landing.partials.icono', ['nombre' => 'check'])Todo lo de Lite</li>
                  <li>@include('landing.partials.icono', ['nombre' => 'check'])Sitio web con plantilla</li>
                  <li>@include('landing.partials.icono', ['nombre' => 'check'])Reservas en línea desde el sitio web del restaurante</li>
                  <li>@include('landing.partials.icono', ['nombre' => 'check'])Galería de fotos del restaurante</li>
                </ul>
                @if ($url = $soporte->enlaceWhatsapp('Hola, quiero informes del plan Aura de '.config('app.name').'.'))
                  <a class="btn btn--primary btn--block" href="{{ $url }}" rel="noopener noreferrer"><span>Pregunta por este plan<span class="sr-only">: Aura</span></span></a>
                @endif
              </article>
            </div>
            @if (filled(config('menuonl.precios.nota')))
              <p class="plans__nota">{{ config('menuonl.precios.nota') }}</p>
            @endif
          </div>
        </div>
      </section>
    </div>
  </div>

  <!-- Los tramos que no caben en la pantalla pasan aquí, en flujo normal, antes de las preguntas. -->
  <div data-escena-flujo></div>

  <!-- 6. Preguntas frecuentes -->
  <section class="section section--ice" id="preguntas" aria-labelledby="titulo-preguntas">
    <div class="wrap faq-wrap">
      <h2 id="titulo-preguntas" data-reveal>Preguntas frecuentes</h2>
      <div class="faq" data-reveal>
        <details>
          <summary><span>¿Mis clientes tienen que descargar algo?</span>@include('landing.partials.icono', ['nombre' => 'abajo'])</summary>
          <p>
            @if ($conNfc)
              No. Escanean el QR o tocan la mesa con su celular y el menú se abre en el navegador. Para tocar la mesa, el celular debe tener NFC.
            @else
              No. Escanean el QR con su celular y el menú se abre en el navegador.
            @endif
          </p>
        </details>
        <details>
          <summary><span>¿Yo puedo cambiar mi menú?</span>@include('landing.partials.icono', ['nombre' => 'abajo'])</summary>
          <p>Sí, en los dos planes. Desde tu panel cambias tus platillos, precios, fotos y horario cuando quieras. Con Aura también editas el sitio web del restaurante.</p>
        </details>
        <details>
          <summary><span>¿Qué incluye cada plan?</span>@include('landing.partials.icono', ['nombre' => 'abajo'])</summary>
          <p><strong>Lite:</strong> menú digital con {{ $codigosEnMesa }}, foto de cada platillo y pedidos por WhatsApp, para domicilio o para recoger. Editas tu menú desde tu panel. No incluye sitio web.</p>
          <p><strong>Aura:</strong> todo lo de Lite, más sitio web con plantilla, reservas en línea y galería de fotos del restaurante.</p>
        </details>
        <details>
          <summary><span>¿Mis clientes pueden pedir y pagar en línea?</span>@include('landing.partials.icono', ['nombre' => 'abajo'])</summary>
          <p>Pueden pedir: arman su pedido en el menú y lo mandan por WhatsApp, para domicilio o para recoger. Por ahora no hay pago en línea.</p>
        </details>
        <details>
          <summary><span>¿Cuánto cuesta y hay contrato?</span>@include('landing.partials.icono', ['nombre' => 'abajo'])</summary>
          <p>Lite cuesta {{ $precioLite }} y Aura {{ $precioAura }}. {{ config('menuonl.precios.nota') }} Sin contrato: cancelas cuando quieras.</p>
        </details>
        <details>
          <summary><span>¿Cuánto tarda en estar listo mi menú y qué soporte tengo?</span>@include('landing.partials.icono', ['nombre' => 'abajo'])</summary>
          <p>Nosotros cargamos tu primer menú. Queda listo en 48 horas. Te atendemos por WhatsApp en horario de oficina.</p>
        </details>
      </div>
    </div>
  </section>

  <!-- 7. Cierre -->
  <section class="closing on-dark" id="contacto" aria-labelledby="titulo-cierre">
    <div class="wrap closing__grid">
      <div class="closing__copy" data-reveal>
        <h2 id="titulo-cierre">Platiquemos de tu restaurante</h2>
        @if ($soporte->enlaceWhatsapp())
          <p>Escríbenos por WhatsApp y te contamos cómo quedaría tu menú.</p>
        @endif
        <div class="actions">
          @if ($url = $soporte->enlaceWhatsapp($mensajeConocer))
            <a class="btn btn--light" href="{{ $url }}" rel="noopener noreferrer">@include('landing.partials.icono', ['nombre' => 'whatsapp'])<span>Escríbenos por WhatsApp</span></a>
          @endif
          @if ($demoUrl)
            <a class="btn btn--ghost" href="{{ $demoUrl }}"><span>Ver un menú de ejemplo</span></a>
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
