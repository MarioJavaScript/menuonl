@props(['etiqueta' => '', 'tono' => 'contexto', 'icono' => null])
@php
    throw_if(blank($etiqueta), \InvalidArgumentException::class, 'x-mb.banner necesita una etiqueta accesible (prop "etiqueta").');
    $tono = $tono === 'atencion' ? 'atencion' : 'contexto';
    $iconoPorDefecto = $tono === 'atencion' ? \Filament\Support\Icons\Heroicon::OutlinedExclamationTriangle : \Filament\Support\Icons\Heroicon::OutlinedEye;
@endphp
{{-- Banner genérico de contexto o atención. Se queda en el flujo de la página: la posición fija y la altura las decide quien lo usa (PR 4). --}}
<div {{ $attributes->class(['mb-banner', 'mb-banner--atencion' => $tono === 'atencion']) }} role="region" aria-label="{{ $etiqueta }}">
    <span class="mb-banner__icono" aria-hidden="true"><x-filament::icon :icon="$icono ?? $iconoPorDefecto" /></span>
    <div class="mb-banner__texto">{{ $slot }}</div>
    @isset($accion)
        <div class="mb-banner__accion">{{ $accion }}</div>
    @endisset
</div>
