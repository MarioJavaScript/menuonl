@php
    $ruta = $getStatePath();
    $actual = $getState();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" role="radiogroup" aria-label="{{ __('panel.apariencia.diseno.campo') }}">
        @foreach ($fichas as $clave => $ficha)
            <label class="group relative flex cursor-pointer flex-col overflow-hidden rounded-xl bg-white ring-1 ring-gray-950/10 transition has-[:checked]:ring-2 has-[:checked]:ring-primary-600 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-600 dark:bg-white/5 dark:ring-white/20 dark:has-[:checked]:ring-primary-400">
                <input
                    type="radio"
                    class="sr-only"
                    name="{{ $ruta }}"
                    value="{{ $clave }}"
                    wire:model.live="{{ $ruta }}"
                />
                <div class="border-b border-gray-200 dark:border-white/10">
                    @include('filament.partials.muestra-plantilla', ['ficha' => $ficha])
                </div>
                <div class="flex flex-1 flex-col gap-1 p-4">
                    <span class="flex items-center justify-between gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $ficha['nombre'] }}
                        @if ($actual === $clave)
                            <x-filament::badge color="primary" icon="heroicon-m-check-circle">{{ __('panel.apariencia.diseno.elegido') }}</x-filament::badge>
                        @endif
                    </span>
                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ $ficha['descripcion'] }}</span>
                    <span class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ __('panel.apariencia.diseno.ideal_para', ['para' => mb_strtolower($ficha['para'])]) }}</span>
                </div>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
