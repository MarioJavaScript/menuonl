@php
    $prodNom = $prod->traduccion($idioma)?->nombre ?? $prod->traduccion()?->nombre ?? 'Platillo';
    $prodDesc = $prod->traduccion($idioma)?->descripcion ?? $prod->traduccion()?->descripcion;
    $esDestacado = (bool) ($prod->destacado ?? false);
    $fmt = fn ($v) => \App\Services\FormatoMoneda::formatear($v, $restaurante->moneda, $idioma);
@endphp

<article class="card flex flex-col justify-between transition-shadow hover:shadow-md relative overflow-hidden" aria-labelledby="dish-title-{{ $prod->id }}">
    @if ($esDestacado)
        <div class="inline-flex items-center gap-1 self-start mb-2 px-2.5 py-0.5 rounded-full text-xs font-extrabold uppercase tracking-wider bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200 border border-amber-200 dark:border-amber-900" aria-label="Platillo recomendado">
            <span aria-hidden="true">⭐</span> Recomendado
        </div>
    @endif

    <div>
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1">
                <h3 id="dish-title-{{ $prod->id }}" class="dish-title text-base sm:text-lg font-bold text-slate-900 dark:text-slate-50 leading-snug">{{ $prodNom }}</h3>
                @if ($prodDesc)
                    <p class="dish-desc text-xs sm:text-sm text-slate-600 dark:text-slate-300 mt-1 leading-relaxed">{{ $prodDesc }}</p>
                @endif

                @if ($prod->etiquetas->isNotEmpty())
                    <ul class="flex flex-wrap gap-1 mt-2.5 list-none" aria-label="Etiquetas dietéticas de {{ $prodNom }}">
                        @foreach ($prod->etiquetas as $etiqueta)
                            <li class="tag inline-flex items-center text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md border border-slate-200/60 dark:border-slate-700">#{{ $etiqueta->codigo }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if (filled($prod->imagen))
                <img src="{{ Storage::url($prod->imagen) }}" alt="" loading="lazy" decoding="async" width="96" height="96" class="dish-img w-20 h-20 sm:w-24 sm:h-24 rounded-xl object-cover shrink-0 bg-slate-100 dark:bg-slate-800 border border-slate-100 dark:border-slate-800 shadow-xs">
            @endif
        </div>

        <!-- Variants presentation if present -->
        @if ($prod->tieneVariantes())
            <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 block">
                    Opciones disponibles:
                </span>
                @foreach ($prod->variantes as $variante)
                    @php
                        $varNom = $variante->traduccion($idioma)?->nombre ?? $variante->traduccion()?->nombre ?? 'Opción';
                    @endphp
                    <div class="flex items-center justify-between text-xs sm:text-sm py-1.5 px-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ $varNom }}</span>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-900 dark:text-slate-50">{{ $fmt($variante->precio) }}</span>
                            @if ($puedePedir)
                                @if ($variante->disponible)
                                    <button
                                        type="button"
                                        onclick="cartAddItem({
                                            producto_id: {{ $prod->id }},
                                            variante_id: {{ $variante->id }},
                                            nombre: @js($prodNom),
                                            variante_nombre: @js($varNom),
                                            precio: {{ $variante->precio }}
                                        })"
                                        aria-label="Agregar {{ $prodNom }} ({{ $varNom }}) al pedido"
                                        class="btn-cart-add min-w-[44px] min-h-[44px] px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-slate-900 dark:focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        <span aria-hidden="true" class="text-sm font-bold">+</span>
                                    </button>
                                @else
                                    <span class="text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 px-2 py-1 rounded text-xs font-bold uppercase" aria-label="{{ $varNom }} agotado">Agotado</span>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Regular price and single add to cart if no variants -->
    @if (! $prod->tieneVariantes())
        <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
            <div class="flex items-center flex-wrap gap-1.5">
                @if ($prod->precio_oferta !== null && (float)$prod->precio_oferta < (float)$prod->precio)
                    <span class="sr-only">Precio original:</span>
                    <span class="dish-price-old">{{ $fmt($prod->precio) }}</span>
                    <span class="sr-only">Precio de oferta:</span>
                    <span class="dish-price-offer">{{ $fmt($prod->precio_oferta) }}</span>
                @else
                    <span class="dish-price">{{ $fmt($prod->precio) }}</span>
                @endif

                @if (! $prod->disponible)
                    <span class="ml-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-200 uppercase">
                        Agotado
                    </span>
                @endif
            </div>

            @if ($puedePedir)
                @if ($prod->disponible)
                    <button
                        type="button"
                        onclick="cartAddItem({
                            producto_id: {{ $prod->id }},
                            nombre: @js($prodNom),
                            precio: {{ $prod->precioVigente() }}
                        })"
                        aria-label="Agregar {{ $prodNom }} al pedido"
                        class="btn-cart-add min-h-[44px] min-w-[44px] px-4 py-2.5 text-xs sm:text-sm gap-2 focus-visible:ring-2 focus-visible:ring-slate-900 dark:focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        <span>Agregar</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </button>
                @else
                    <button type="button" disabled aria-disabled="true" class="btn-cart-add min-h-[44px] min-w-[44px] px-4 py-2.5 text-xs sm:text-sm">
                        Agotado
                    </button>
                @endif
            @endif
        </div>
    @endif
</article>
