<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="color-scheme" content="light">
    <script src="{{ asset('js/apariencia.js').'?v='.filemtime(public_path('js/apariencia.js')) }}"></script>
    <title>{{ __('panel.plan_sin_panel.titulo') }}</title>
    <x-mb.favicons />
    <style>
        :root {
            --fondo: #f6f5f2;
            --tarjeta: #ffffff;
            --texto: #1f1f24;
            --suave: #55555e;
            --borde: #dcdad4;
            --acento: #8a5300;
            --acento-texto: #ffffff;
        }
        :root[data-apariencia="dark"] {
            --fondo: #17171b;
            --tarjeta: #212126;
            --texto: #f2f1ee;
            --suave: #b9b8c0;
            --borde: #3a3a42;
            --acento: #f0b34a;
            --acento-texto: #1a1204;
        }
        @media (prefers-color-scheme: dark) {
            :root[data-apariencia="system"] {
                --fondo: #17171b;
                --tarjeta: #212126;
                --texto: #f2f1ee;
                --suave: #b9b8c0;
                --borde: #3a3a42;
                --acento: #f0b34a;
                --acento-texto: #1a1204;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: var(--fondo);
            color: var(--texto);
            font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        main {
            width: 100%;
            max-width: 480px;
            padding: 24px 20px;
            background: var(--tarjeta);
            border: 1px solid var(--borde);
            border-radius: 12px;
        }
        .marca { margin: 0 0 16px; }
        :root[data-apariencia="dark"] .mb-logo--claro { display: none; }
        :root[data-apariencia="dark"] .mb-logo--oscuro { display: inline; }
        @media (prefers-color-scheme: dark) {
            :root[data-apariencia="system"] .mb-logo--claro { display: none; }
            :root[data-apariencia="system"] .mb-logo--oscuro { display: inline; }
        }
        h1 { margin: 0 0 12px; font-size: 1.5rem; line-height: 1.25; }
        p { margin: 0 0 16px; }
        .restaurantes { margin: 0 0 16px; padding-left: 20px; font-weight: 600; }
        .restaurantes li { margin-bottom: 4px; }
        .suave { color: var(--suave); }
        .acciones { display: flex; flex-direction: column; gap: 12px; margin-top: 24px; }
        .boton {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 12px 20px;
            border-radius: 8px;
            border: 2px solid var(--acento);
            font: inherit;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            width: 100%;
        }
        .acciones form { display: flex; flex: 1 1 0; margin: 0; }
        .boton-principal { background: var(--acento); color: var(--acento-texto); }
        .boton-secundario { background: transparent; color: var(--texto); border-color: var(--borde); }
        .boton:focus-visible { outline: 3px solid var(--texto); outline-offset: 2px; }
        @media (min-width: 520px) {
            main { padding: 32px; }
            .acciones { flex-direction: row; }
            .boton { width: auto; flex: 1 1 0; }
        }
    </style>
</head>
<body>
<main>
    <div class="marca"><x-mb.logo variante="horizontal" :alto="32" /></div>

    <h1>{{ __('panel.plan_sin_panel.titulo') }}</h1>

    @if (count($restaurantes) === 1)
        <p>{{ __('panel.plan_sin_panel.uno', ['restaurante' => $restaurantes[0]]) }}</p>
    @else
        <p>{{ __('panel.plan_sin_panel.varios') }}</p>
        <ul class="restaurantes">
            @foreach ($restaurantes as $nombre)
                <li>{{ $nombre }}</li>
            @endforeach
        </ul>
    @endif

    <p>{{ __('panel.plan_sin_panel.proveedor') }}</p>

    @if ($enlaceWhatsapp !== null)
        <p class="suave">{{ __('panel.plan_sin_panel.con_whatsapp') }}</p>
    @else
        <p class="suave">{{ __('panel.plan_sin_panel.sin_whatsapp') }}</p>
    @endif

    <div class="acciones">
        @if ($enlaceWhatsapp !== null)
            <a class="boton boton-principal" href="{{ $enlaceWhatsapp }}" target="_blank" rel="noopener noreferrer">{{ __('panel.plan_sin_panel.boton_whatsapp') }}</a>
        @endif
        <form method="POST" action="{{ $urlCerrarSesion }}">
            @csrf
            <button type="submit" class="boton boton-secundario">{{ __('panel.plan_sin_panel.cerrar_sesion') }}</button>
        </form>
    </div>
</main>
</body>
</html>
