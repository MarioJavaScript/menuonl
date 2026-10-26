@props(['plan' => null, 'sinTexto' => false])
@php
    $plan = $plan instanceof \App\Enums\PlanRestaurante ? $plan : \App\Enums\PlanRestaurante::desde(is_string($plan) ? $plan : null);
    $nivel = $plan->nivel();
@endphp
{{-- Glifo de plan: tres barras (altura creciente, las del nivel llenas) y el nombre; el nivel nunca se transmite solo con color. --}}
<span {{ $attributes->class(['mb-plan']) }} data-nivel="{{ $nivel }}">
    <span class="mb-plan__glifo" aria-hidden="true">
        @foreach ([1, 2, 3] as $barra)
            <i @class(['mb-plan__llena' => $barra <= $nivel])></i>
        @endforeach
    </span>
    @if ($sinTexto)
        <span class="mb-sr">{{ __('panel.componentes_mb.glifo_plan.accesible', ['nombre' => $plan->getLabel()]) }}</span>
    @else
        <span>{{ $plan->getLabel() }}</span>
    @endif
</span>
