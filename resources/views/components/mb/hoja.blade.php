@props(['titulo' => null, 'descripcion' => null, 'nivel' => 2, 'sinRelleno' => false])
@php
    $encabezado = 'h'.(in_array((int) $nivel, [2, 3, 4], true) ? (int) $nivel : 2);
    $idTitulo = filled($titulo) ? 'mb-hoja-'.\Illuminate\Support\Str::random(8) : null;
@endphp
{{-- Hoja: tarjeta de superficie con cabecera opcional (título, descripción y acciones). Con título es una región nombrada. --}}
<section {{ $attributes->class(['mb-hoja', 'mb-hoja--sin-relleno' => $sinRelleno]) }} @if ($idTitulo) aria-labelledby="{{ $idTitulo }}" @endif>
    @if ($idTitulo)
        <header class="mb-hoja__cab">
            <div>
                <{{ $encabezado }} id="{{ $idTitulo }}" class="mb-hoja__titulo">{{ $titulo }}</{{ $encabezado }}>
                @if (filled($descripcion))
                    <p class="mb-hoja__descripcion">{{ $descripcion }}</p>
                @endif
            </div>
            @isset($acciones)
                <div class="mb-hoja__acciones">{{ $acciones }}</div>
            @endisset
        </header>
    @endif
    <div class="mb-hoja__cuerpo">{{ $slot }}</div>
</section>
