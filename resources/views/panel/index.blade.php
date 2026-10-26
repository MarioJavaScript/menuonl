<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Panel · {{ config('app.name') }}</title>
    <x-mb.favicons />
    @vite(['resources/css/panel.css', 'resources/js/panel.js'])
</head>
<body>
<div class="shell">
    <nav class="nav" aria-label="Principal">
        <div class="brand"><span id="rest-nombre">Panel</span><small>Panel de gestión</small></div>
        <a href="#inicio" data-r="inicio" data-modulo="inicio"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 11l9-8 9 8M5 10v10h14V10"/></svg>Inicio</a>
        <a href="#reservas" data-r="reservas" data-modulo="reservas"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>Reservas<span class="badge" id="b-res" hidden></span></a>
        <a href="#menu" data-r="menu" data-modulo="menu"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 3v8a2 2 0 002 2v8M10 3v8M8 3v18M18 21V3c-3 2-3 6-3 9h3"/></svg>Menú</a>
        <a href="#pedidos" data-r="pedidos" data-modulo="pedidos"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 4h14l-1 16H6L5 4zM9 8h6"/></svg>Pedidos<span class="badge" id="b-ped" hidden></span></a>
        <a href="#ajustes" data-r="ajustes" data-modulo="ajustes"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/></svg>Ajustes</a>
    </nav>
    <main id="main" tabindex="-1"><p class="muted">Cargando…</p></main>
</div>
<div id="toast" role="status" aria-live="polite"></div>
<dialog id="dlg"><p id="dlg-msg"></p><div class="row"><button class="btn" id="dlg-no" type="button">Cancelar</button><button class="btn primary" id="dlg-yes" type="button">Confirmar</button></div></dialog>
</body>
</html>
