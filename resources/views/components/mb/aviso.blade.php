@props(['tono' => 'info', 'titulo' => null, 'icono' => null, 'anuncia' => true])
@php
    $iconos = [
        'info' => \Filament\Support\Icons\Heroicon::OutlinedInformationCircle,
        'exito' => \Filament\Support\Icons\Heroicon::OutlinedCheckCircle,
        'atencion' => \Filament\Support\Icons\Heroicon::OutlinedExclamationTriangle,
        'error' => \Filament\Support\Icons\Heroicon::OutlinedExclamationCircle,
        'neutro' => \Filament\Support\Icons\Heroicon::OutlinedLightBulb,
    ];
    $tono = array_key_exists((string) $tono, $iconos) ? (string) $tono : 'info';
    // Solo el error interrumpe al lector de pantalla; el resto se anuncia con cortesía. Con :anuncia="false" no lleva rol (aviso estático).
    $rol = $anuncia ? ($tono === 'error' ? 'alert' : 'status') : null;
@endphp
<div {{ $attributes->class(['mb-aviso', 'mb-aviso--'.$tono]) }} @if ($rol) role="{{ $rol }}" @endif>
    <span class="mb-aviso__icono" aria-hidden="true"><x-filament::icon :icon="$icono ?? $iconos[$tono]" /></span>
    <div class="mb-aviso__cuerpo">
        @if (filled($titulo))
            <strong>{{ $titulo }}</strong>
        @endif
        @if ($slot->isNotEmpty())
            <span>{{ $slot }}</span>
        @endif
        @isset($accion)
            {{ $accion }}
        @endisset
    </div>
</div>
