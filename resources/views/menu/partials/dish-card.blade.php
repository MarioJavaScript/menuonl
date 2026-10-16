@php
    $prodNom = $prod->traduccion($idioma)?->nombre ?? $prod->traduccion()?->nombre ?? 'Platillo';
    $prodDesc = $prod->traduccion($idioma)?->descripcion ?? $prod->traduccion()?->descripcion;
@endphp

<article class="card flex flex-col justify-between">
    <div>
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1">
                <h3 class="dish-title">{{ $prodNom }}</h3>
                @if ($prodDesc)
                    <p class="dish-desc">{{ $prodDesc }}</p>
                @endif

                @if ($prod->etiquetas->isNotEmpty())
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach ($prod->etiquetas as $etiqueta)
                            <span class="tag">#{{ $etiqueta->codigo }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            @if (filled($prod->imagen))
                <img src="{{ Storage::url($prod->imagen) }}" alt="{{ $prodNom }}" class="dish-img">
            @endif
        </div>

        <!-- Variants presentation if present -->
        @if ($prod->tieneVariantes())
            <div class="mt-3 pt-3 border-t border-slate-100 space-y-2">
                @foreach ($prod->variantes as $variante)
                    @php
                        $varNom = $variante->traduccion($idioma)?->nombre ?? $variante->traduccion()?->nombre ?? 'Opción';
                    @endphp
                    <div class="flex items-center justify-between text-xs py-1">
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
                                            nombre: '{{ addslashes($prodNom) }}',
                                            variante_nombre: '{{ addslashes($varNom) }}',
                                            precio: {{ $variante->precio }}
                                        })"
                                        class="px-2 py-1 rounded bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition-colors"
                                    >
                                        +
                                    </button>
                                @else
                                    <span class="text-rose-600 font-bold text-[10px] uppercase">Agotado</span>
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
            <div class="flex items-center">
                @if ($prod->precio_oferta !== null && (float)$prod->precio_oferta < (float)$prod->precio)
                    <span class="dish-price-old">${{ number_format((float)$prod->precio, 2) }}</span>
                    <span class="dish-price-offer">${{ number_format((float)$prod->precio_oferta, 2) }} {{ $restaurante->moneda }}</span>
                @else
                    <span class="dish-price">${{ number_format((float)$prod->precio, 2) }} {{ $restaurante->moneda }}</span>
                @endif

                @if (! $prod->disponible)
                    <span class="ml-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 uppercase">
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
                            nombre: '{{ addslashes($prodNom) }}',
                            precio: {{ $prod->precioVigente() }}
                        })"
                        class="btn-cart-add"
                    >
                        <span>Agregar</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </button>
                @else
                    <button type="button" disabled class="btn-cart-add opacity-50 cursor-not-allowed">
                        Agotado
                    </button>
                @endif
            @endif
        </div>
    @endif
</article>
