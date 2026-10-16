<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <title>@yield('titulo') · {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f4f5; color: #18181b; margin: 0; }
        main { max-width: 26rem; margin: 3rem auto; padding: 2rem 1.5rem; background: #fff; border-radius: .75rem; }
        label { display: block; margin-top: 1rem; font-weight: 600; }
        input { width: 100%; box-sizing: border-box; padding: .65rem; margin-top: .25rem; border: 1px solid #a1a1aa; border-radius: .4rem; }
        button, .boton { display: inline-block; margin-top: 1.25rem; min-height: 44px; padding: .65rem 1.25rem; border: 0; border-radius: .4rem; background: #d97706; color: #fff; font-weight: 600; text-decoration: none; cursor: pointer; }
        .error { color: #b91c1c; }
    </style>
</head>
<body>
<main>
    @yield('contenido')
</main>
</body>
</html>
