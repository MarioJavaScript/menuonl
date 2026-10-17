@php
    $prodNom = $prod->traduccion($idioma)?->nombre ?? $prod->traduccion()?->nombre ?? 'Platillo';
    $prodDesc = $prod->traduccion($idioma)?->descripcion ?? $prod->traduccion()?->descripcion;
    $esDestacado = (bool) ($prod->destacado ?? false);
@endphp

<article class="card flex flex-col justify-between transition-shadow hover:shadow-md relative overflow-hidden" aria-labelledby="dish-title-{{ $prod->id }}">
    @if ($esDestacado)
        <div class="inline-flex items-center gap-1 self-start mb-2 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-amber-100 text-amber-900 border border-amber-200" aria-label="Platillo recomendado">
            <span aria-hidden="true">⭐</span> Recomendado
        </div>
    @endif

    <div>
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1">
                <h3 id="dish-title-{{ $prod->id }}" class="dish-title text-base sm:text-lg font-bold text-slate-900 leading-snug">{{ $prodNom }}</h3>
                @if ($prodDesc)
                    <p class="dish-desc text-xs sm:text-sm text-slate-600 mt-1 leading-relaxed">{{ $prodDesc }}</p>
                @endif

                @if ($prod->etiquetas->isNotEmpty())
                    <div class="flex flex-wrap gap-1 mt-2.5" role="list" aria-label="Etiquetas dietéticas de {{ $prodNom }}">
                        @foreach ($prod->etiquetas as $etiqueta)
                            <span class="tag inline-flex items-center text-[11px] font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200/60" role="listitem">#{{ $etiqueta->codigo }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            @if (filled($prod->imagen))
                <img src="{{ Storage::url($prod->imagen) }}" alt="{{ $prodNom }}" loading="lazy" class="dish-img w-20 h-20 sm:w-24 sm:h-24 rounded-xl object-cover shrink-0 bg-slate-100 border border-slate-100 shadow-xs">
            @endif
        </div>

        <!-- Variants presentation if present -->
        @if ($prod->tieneVariantes())
            <div class="mt-3 pt-3 border-t border-slate-100 space-y-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600 block">
                    Opciones disponibles:
                </span>
                @foreach ($prod->variantes as $variante)
                    @php
                        $varNom = $variante->traduccion($idioma)?->nombre ?? $variante->traduccion()?->nombre ?? 'Opción';
                    @endphp
                    <div class="flex items-center justify-between text-xs sm:text-sm py-1.5 px-2 rounded-lg hover:bg-slate-50 transition-colors">
                        <span class="font-medium text-slate-700">{{ $varNom }}</span>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-900">${{ number_format((float)$variante->precio, 2) }} {{ $restaurante->moneda }}</span>
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
                                        class="min-w-[44px] min-h-[44px] px-3 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 active:scale-95 text-white font-bold text-xs inline-flex items-center justify-center transition-all focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        <span aria-hidden="true" class="text-sm font-bold">+</span>
                                    </button>
                                @else
                                    <span class="text-rose-700 bg-rose-50 border border-rose-200 px-2 py-1 rounded text-[11px] font-bold uppercase" aria-label="{{ $varNom }} agotado">Agotado</span>
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
        <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100">
            <div class="flex items-center flex-wrap gap-1.5">
                @if ($prod->precio_oferta !== null && (float)$prod->precio_oferta < (float)$prod->precio)
                    <span class="sr-only">Precio original:</span>
                    <span class="dish-price-old">${{ number_format((float)$prod->precio, 2) }}</span>
                    <span class="sr-only">Precio de oferta:</span>
                    <span class="dish-price-offer">${{ number_format((float)$prod->precio_oferta, 2) }} {{ $restaurante->moneda }}</span>
                @else
                    <span class="dish-price">${{ number_format((float)$prod->precio, 2) }} {{ $restaurante->moneda }}</span>
                @endif

                @if (! $prod->disponible)
                    <span class="ml-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 uppercase">
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
                        class="btn-cart-add min-h-[44px] min-w-[44px] px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 active:scale-95 text-white font-bold text-xs sm:text-sm inline-flex items-center justify-center gap-2 transition-all shadow-xs focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        <span>Agregar</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </button>
                @else
                    <button type="button" disabled aria-disabled="true" class="btn-cart-add min-h-[44px] min-w-[44px] px-4 py-2.5 rounded-xl bg-slate-200 text-slate-500 font-bold text-xs sm:text-sm inline-flex items-center justify-center cursor-not-allowed opacity-60">
                        Agotado
                    </button>
                @endif
            @endif
        </div>
    @endif
</article>
