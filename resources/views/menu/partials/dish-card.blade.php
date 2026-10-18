@php
    $prodNom = $prod->traduccion($idioma)?->nombre ?? $prod->traduccion()?->nombre ?? 'Platillo';
    $prodDesc = $prod->traduccion($idioma)?->descripcion ?? $prod->traduccion()?->descripcion;
    $esDestacado = (bool) ($prod->destacado ?? false);
    $fmt = fn ($v) => \App\Services\FormatoMoneda::formatear($v, $restaurante->moneda, $idioma);
@endphp

<article class="plato" aria-labelledby="dish-title-{{ $prod->id }}">
    @if ($esDestacado)
        <div class="chip-recomendado" aria-label="Platillo recomendado">
            <span aria-hidden="true">⭐</span> Recomendado
        </div>
    @endif

    <div>
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <h3 id="dish-title-{{ $prod->id }}" class="dish-title">{{ $prodNom }}</h3>
                @if ($prodDesc)
                    <p class="dish-desc mt-1">{{ $prodDesc }}</p>
                @endif

                @if ($prod->etiquetas->isNotEmpty())
                    <ul class="flex flex-wrap gap-1 mt-2.5 list-none" aria-label="Etiquetas dietéticas de {{ $prodNom }}">
                        @foreach ($prod->etiquetas as $etiqueta)
                            <li class="tag inline-flex items-center">#{{ $etiqueta->codigo }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if (filled($prod->imagen))
                <img src="{{ Storage::url($prod->imagen) }}" alt="" loading="lazy" decoding="async" width="96" height="96" class="dish-img">
            @endif
        </div>

        <!-- Variants presentation if present -->
        @if ($prod->tieneVariantes())
            <div class="mt-3">
                <span class="text-xs font-bold uppercase tracking-wider text-texto-suave block mb-1">
                    Opciones disponibles:
                </span>
                @foreach ($prod->variantes as $variante)
                    @php
                        $varNom = $variante->traduccion($idioma)?->nombre ?? $variante->traduccion()?->nombre ?? 'Opción';
                    @endphp
                    <div class="variante-fila">
                        <span class="font-medium text-texto-medio">{{ $varNom }}</span>
                        <div class="flex items-center gap-3">
                            <span class="dish-price">{{ $fmt($variante->precio) }}</span>
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
                                        class="btn-cart-add min-w-[44px] min-h-[44px] px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-foco focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        <span aria-hidden="true" class="text-sm font-bold">+</span>
                                    </button>
                                @else
                                    <span class="etiqueta-agotado" aria-label="{{ $varNom }} agotado">Agotado</span>
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
        <div class="flex items-center justify-between mt-4">
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
                    <span class="etiqueta-agotado ml-1">
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
                        class="btn-cart-add min-h-[44px] min-w-[44px] px-4 py-2.5 text-xs sm:text-sm gap-2 focus-visible:ring-2 focus-visible:ring-foco focus-visible:ring-offset-2 focus-visible:outline-none"
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
