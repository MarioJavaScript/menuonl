@props(['titulo', 'texto' => null, 'icono' => null, 'nivel' => 2])
@php
    $encabezado = 'h'.(in_array((int) $nivel, [2, 3, 4], true) ? (int) $nivel : 2);
@endphp
{{-- Estado vacío: ícono (o arte en la ranura "arte") + título + texto + acción opcional. Sin ilustraciones propias todavía. --}}
<div {{ $attributes->class(['mb-vacio']) }}>
    @if (isset($arte) && $arte->isNotEmpty())
        <div class="mb-vacio__arte">{{ $arte }}</div>
    @else
        <span class="mb-vacio__icono" aria-hidden="true"><x-filament::icon :icon="$icono ?? \Filament\Support\Icons\Heroicon::OutlinedInbox" /></span>
    @endif
    <{{ $encabezado }} class="mb-vacio__titulo">{{ $titulo }}</{{ $encabezado }}>
    @if (filled($texto))
        <p class="mb-vacio__texto">{{ $texto }}</p>
    @endif
    @isset($accion)
        <div class="mb-vacio__accion">{{ $accion }}</div>
    @endisset
</div>
