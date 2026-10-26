{{-- Muestra de los componentes x-mb.* (PR 3). Solo se ve en el entorno local, dentro de la página Componentes; no guarda nada. --}}
<div style="display: grid; gap: 2rem">
    <div>
        <h3 style="margin: 0 0 0.25rem; font-weight: 600">{{ __('panel.componentes_mb.glifo_plan.titulo') }}</h3>
        <p style="margin: 0 0 0.75rem; color: var(--mb-tinta-2)">{{ __('panel.componentes_mb.glifo_plan.descripcion') }}</p>
        <div style="display: flex; flex-wrap: wrap; gap: 1.5rem">
            @foreach (\App\Enums\PlanRestaurante::cases() as $plan)
                <x-mb.glifo-plan :plan="$plan" />
            @endforeach
        </div>
        <p style="margin: 0.75rem 0 0.5rem; color: var(--mb-tinta-2)">{{ __('panel.componentes_mb.glifo_plan.sin_texto') }}</p>
        <div style="display: flex; flex-wrap: wrap; gap: 1.5rem">
            @foreach (\App\Enums\PlanRestaurante::cases() as $plan)
                <x-mb.glifo-plan :plan="$plan" sin-texto />
            @endforeach
        </div>
    </div>

    <div>
        <h3 style="margin: 0 0 0.25rem; font-weight: 600">{{ __('panel.componentes_mb.aviso.titulo') }}</h3>
        <p style="margin: 0 0 0.75rem; color: var(--mb-tinta-2)">{{ __('panel.componentes_mb.aviso.descripcion') }}</p>
        <div style="display: grid; gap: 0.75rem">
            @foreach (['info', 'exito', 'atencion', 'error', 'neutro'] as $tono)
                <x-mb.aviso :tono="$tono" :titulo="__('panel.componentes_mb.aviso.'.$tono.'_titulo')">
                    {{ __('panel.componentes_mb.aviso.'.$tono) }}
                    @if ($tono === 'info')
                        <x-slot:accion><a href="#" class="fi-link">{{ __('panel.componentes_mb.aviso.accion') }}</a></x-slot:accion>
                    @endif
                </x-mb.aviso>
            @endforeach
            <x-mb.aviso tono="atencion" :anuncia="false" :titulo="__('panel.componentes_mb.aviso.estatico')">
                {{ __('panel.componentes_mb.aviso.atencion') }}
            </x-mb.aviso>
        </div>
    </div>

    <div>
        <h3 style="margin: 0 0 0.25rem; font-weight: 600">{{ __('panel.componentes_mb.vacio.titulo') }}</h3>
        <p style="margin: 0 0 0.75rem; color: var(--mb-tinta-2)">{{ __('panel.componentes_mb.vacio.descripcion') }}</p>
        <x-mb.vacio :titulo="__('panel.componentes_mb.vacio.encabezado')" :texto="__('panel.componentes_mb.vacio.texto')" :nivel="4">
            <x-slot:accion><a href="#" class="fi-link">{{ __('panel.componentes_mb.vacio.accion') }}</a></x-slot:accion>
        </x-mb.vacio>
    </div>

    <div>
        <h3 style="margin: 0 0 0.25rem; font-weight: 600">{{ __('panel.componentes_mb.hoja.titulo') }}</h3>
        <p style="margin: 0 0 0.75rem; color: var(--mb-tinta-2)">{{ __('panel.componentes_mb.hoja.descripcion') }}</p>
        <x-mb.hoja :titulo="__('panel.componentes_mb.hoja.encabezado')" :descripcion="__('panel.componentes_mb.hoja.descripcion')" :nivel="4">
            <x-slot:acciones><a href="#" class="fi-link">{{ __('panel.componentes_mb.hoja.accion') }}</a></x-slot:acciones>
            {{ __('panel.componentes_mb.hoja.texto_hoja') }}
        </x-mb.hoja>
    </div>

    <div>
        <h3 style="margin: 0 0 0.25rem; font-weight: 600">{{ __('panel.componentes_mb.banner.titulo') }}</h3>
        <p style="margin: 0 0 0.75rem; color: var(--mb-tinta-2)">{{ __('panel.componentes_mb.banner.descripcion') }}</p>
        <div style="display: grid; gap: 0.75rem">
            <x-mb.banner :etiqueta="__('panel.componentes_mb.banner.etiqueta_contexto')">
                {{ __('panel.componentes_mb.banner.contexto') }}
                <x-mb.glifo-plan :plan="\App\Enums\PlanRestaurante::Completo" />
                <x-slot:accion><a href="#">{{ __('panel.componentes_mb.banner.accion_contexto') }}</a></x-slot:accion>
            </x-mb.banner>
            <x-mb.banner tono="atencion" :etiqueta="__('panel.componentes_mb.banner.etiqueta_atencion')">
                {{ __('panel.componentes_mb.banner.atencion') }}
            </x-mb.banner>
        </div>
    </div>
</div>
