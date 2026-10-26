{{--
    Logo oficial de Menubly (public/brand). Alterna claro/oscuro solo con CSS: dos <img>, y la de modo oscuro lleva `hidden`
    (las reglas están en public/css/menuonl.css para lo público y en el tema de Filament para /admin; sin JavaScript).
    Siempre con width/height explícitos (los del SVG original son enormes). `decorativo` deja el alt vacío: úsalo cuando
    el nombre ya aparece como texto junto al logo.
--}}
@props(['variante' => 'horizontal', 'alto' => 32, 'decorativo' => false])
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
<img {{ $attributes->class(['mb-logo', 'mb-logo--claro']) }} src="{{ $url($datos['claro']) }}" alt="{{ $alt }}" width="{{ $ancho }}" height="{{ $alto }}" decoding="async">
@if ($datos['oscuro'] !== null)
    <img {{ $attributes->class(['mb-logo', 'mb-logo--oscuro']) }} src="{{ $url($datos['oscuro']) }}" alt="{{ $alt }}" width="{{ $ancho }}" height="{{ $alto }}" decoding="async" loading="lazy" hidden>
@endif
