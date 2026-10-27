{{--
    Logo oficial de Menubly (public/brand). Alterna claro/oscuro solo con CSS: dos <img> sin el atributo `hidden` (el
    preflight de Tailwind lo fuerza con !important y ninguna regla de modo oscuro podría mostrarla). La de modo oscuro se
    oculta por omisión con `.mb-logo--oscuro { display: none }` y se muestra en oscuro; las reglas están en
    public/css/menuonl.css para lo público, en el tema de Filament para /admin y en plan/sin-panel; sin JavaScript.
    `lazy` solo para logos fuera de la primera pantalla (el pie del menú); los de cabecera cargan de inmediato.
    Siempre con width/height explícitos (los del SVG original son enormes). `decorativo` deja el alt vacío: úsalo cuando
    el nombre ya aparece como texto junto al logo.
--}}
@props(['variante' => 'horizontal', 'alto' => 32, 'decorativo' => false, 'lazy' => false])
@php
    $variantes = [
        'horizontal' => ['proporcion' => 305.05 / 64, 'claro' => 'menubly-horizontal-color.svg', 'oscuro' => 'menubly-horizontal-negativo.svg'],
        'wordmark' => ['proporcion' => 398.3 / 90, 'claro' => 'menubly-wordmark-color.svg', 'oscuro' => 'menubly-wordmark-negativo.svg'],
        'simbolo' => ['proporcion' => 1.0, 'claro' => 'menubly-simbolo-color.svg', 'oscuro' => null],
    ];
    $datos = $variantes[$variante] ?? $variantes['horizontal'];
    $alto = max(1, (int) $alto);
    $ancho = (int) round($alto * $datos['proporcion']);
    $alt = $decorativo ? '' : (string) config('app.name');
    $url = fn (string $archivo): string => asset('brand/'.$archivo).'?v='.filemtime(public_path('brand/'.$archivo));
@endphp
<img {{ $attributes->class(['mb-logo', 'mb-logo--claro']) }} src="{{ $url($datos['claro']) }}" alt="{{ $alt }}" width="{{ $ancho }}" height="{{ $alto }}" decoding="async"@if ($lazy) loading="lazy"@endif>
@if ($datos['oscuro'] !== null)
    <img {{ $attributes->class(['mb-logo', 'mb-logo--oscuro']) }} src="{{ $url($datos['oscuro']) }}" alt="{{ $alt }}" width="{{ $ancho }}" height="{{ $alto }}" decoding="async"@if ($lazy) loading="lazy"@endif>
@endif
