<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $restaurante->traduccion($idioma)->nombre }} — Menú Digital</title>
        <style>
            :root {
                --font-sans: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: var(--font-sans);
                background-color: #f8fafc;
                color: #1e293b;
                line-height: 1.5;
                padding-bottom: 6rem;
            }
            .container {
                max-width: 48rem;
                margin-left: auto;
                margin-right: auto;
                padding-left: 1rem;
                padding-right: 1rem;
            }
            .cover-img {
                width: 100%;
                height: 12rem;
                object-fit: cover;
                background-color: #e2e8f0;
            }
            .logo-img {
                width: 5.5rem;
                height: 5.5rem;
                border-radius: 1rem;
                border: 4px solid #ffffff;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
                object-fit: cover;
                background-color: #ffffff;
                margin-top: -2.75rem;
            }
            .badge-open {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                padding: 0.25rem 0.65rem;
                border-radius: 9999px;
                font-size: 0.75rem;
                font-weight: 700;
                background-color: #dcfce7;
                color: #15803d;
            }
            .badge-closed {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                padding: 0.25rem 0.65rem;
                border-radius: 9999px;
                font-size: 0.75rem;
                font-weight: 700;
                background-color: #fee2e2;
                color: #b91c1c;
            }
            .badge-mesa {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                padding: 0.25rem 0.65rem;
                border-radius: 9999px;
                font-size: 0.75rem;
                font-weight: 700;
                background-color: #e0e7ff;
                color: #3730a3;
            }
            .sticky-nav {
                position: sticky;
                top: 0;
                z-index: 30;
                background-color: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(8px);
                border-bottom: 1px solid #e2e8f0;
                padding: 0.75rem 0;
                overflow-x: auto;
                white-space: nowrap;
                scrollbar-width: none;
            }
            .sticky-nav::-webkit-scrollbar { display: none; }
            .nav-pill {
                display: inline-block;
                padding: 0.4rem 0.85rem;
                border-radius: 9999px;
                font-size: 0.8125rem;
                font-weight: 600;
                color: #475569;
                text-decoration: none;
                margin-right: 0.5rem;
                background-color: #f1f5f9;
                transition: background-color 0.15s, color 0.15s;
            }
            .nav-pill:hover, .nav-pill:focus {
                background-color: #0f172a;
                color: #ffffff;
            }
            .card {
                background: #ffffff;
                border-radius: 1rem;
                border: 1px solid #e2e8f0;
                padding: 1.25rem;
                box-shadow: 0 1px 3px 0 rgba(0,0,0,0.05);
                margin-bottom: 1rem;
            }
            .dish-img {
                width: 5.5rem;
                height: 5.5rem;
                border-radius: 0.75rem;
                object-fit: cover;
                flex-shrink: 0;
            }
            .dish-title {
                font-size: 1rem;
                font-weight: 700;
                color: #0f172a;
            }
            .dish-desc {
                font-size: 0.8125rem;
                color: #64748b;
                margin-top: 0.25rem;
                line-height: 1.4;
            }
            .dish-price {
                font-size: 0.9375rem;
                font-weight: 700;
                color: #0f172a;
            }
            .dish-price-old {
                font-size: 0.8125rem;
                color: #64748b;
                text-decoration: line-through;
                margin-right: 0.35rem;
            }
            .dish-price-offer {
                font-size: 0.9375rem;
                font-weight: 700;
                color: #be123c;
            }
            .tag {
                display: inline-block;
                padding: 0.15rem 0.5rem;
                border-radius: 0.375rem;
                font-size: 0.6875rem;
                font-weight: 600;
                background-color: #f1f5f9;
                color: #475569;
                margin-right: 0.25rem;
                margin-top: 0.35rem;
            }
            .btn-cart-add {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.35rem;
                min-height: 44px;
                min-width: 44px;
                padding: 0.5rem 1rem;
                border-radius: 0.75rem;
                font-size: 0.8125rem;
                font-weight: 700;
                background-color: #0f172a;
                color: #ffffff;
                border: none;
                cursor: pointer;
                transition: background-color 0.15s, transform 0.1s;
            }
            .btn-cart-add:hover { background-color: #1e293b; }
            .btn-cart-add:active { transform: scale(0.98); }
            .btn-cart-add:disabled {
                background-color: #cbd5e1;
                color: #64748b;
                cursor: not-allowed;
                transform: none;
            }
            .floating-cart-bar {
                position: fixed;
                bottom: 1rem;
                left: 1rem;
                right: 1rem;
                max-width: 46rem;
                margin-left: auto;
                margin-right: auto;
                background-color: #0f172a;
                color: #ffffff;
                border: none;
                font-family: inherit;
                font-size: inherit;
                border-radius: 1rem;
                padding: 0.85rem 1.25rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
                z-index: 40;
                cursor: pointer;
                text-align: left;
                transition: transform 0.15s ease, background-color 0.15s;
            }
            .floating-cart-bar:hover { background-color: #1e293b; }
            .floating-cart-bar:focus-visible {
                outline: 2px solid #ffffff;
                outline-offset: 2px;
            }
            :focus-visible {
                outline: 2px solid #0f172a;
                outline-offset: 2px;
            }
            .cart-toast {
                position: fixed;
                top: 1.25rem;
                left: 50%;
                transform: translateX(-50%) translateY(-20px);
                background-color: #0f172a;
                color: #ffffff;
                padding: 0.65rem 1.25rem;
                border-radius: 9999px;
                font-size: 0.875rem;
                font-weight: 600;
                box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
                z-index: 60;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.25s ease, transform 0.25s ease;
            }
            .cart-toast.visible {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
                pointer-events: auto;
            }
            .sr-only {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border-width: 0;
            }
            .cart-modal-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background-color: rgba(15, 23, 42, 0.6);
                z-index: 50;
                align-items: flex-end;
                justify-content: center;
            }
            .cart-modal-backdrop.open { display: flex; }
            .cart-drawer {
                background: #ffffff;
                width: 100%;
                max-width: 48rem;
                max-height: 90vh;
                overflow-y: auto;
                border-top-left-radius: 1.5rem;
                border-top-right-radius: 1.5rem;
                padding: 1.5rem;
            }
        </style>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-slate-50 text-slate-800">
        <!-- Cart Toast Notification -->
        <div id="cartToast" role="status" aria-live="polite" class="cart-toast" aria-atomic="true">
            <span class="text-emerald-400 font-bold" aria-hidden="true">✓</span>
            <span id="cartToastMessage">Agregado al pedido</span>
        </div>

        @php
            $puedePedir = $restaurante->pedidos_activos && $estaAbierto && filled($restaurante->whatsapp_pedidos);
        @endphp

        <!-- Header -->
        <header class="bg-white border-b border-slate-200">
            @if (filled($restaurante->portada))
                <img src="{{ Storage::url($restaurante->portada) }}" alt="{{ $restaurante->nombre }}" class="cover-img w-full h-48 object-cover bg-slate-200">
            @endif

            <div class="container mx-auto px-4 pb-4">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div class="flex items-start gap-4">
                        @if (filled($restaurante->logo))
                            <img src="{{ Storage::url($restaurante->logo) }}" alt="{{ $restaurante->nombre }}" class="logo-img w-20 h-20 rounded-2xl border-4 border-white shadow-md object-cover bg-white {{ filled($restaurante->portada) ? '-mt-10' : 'mt-4' }}">
                        @endif

                        <div class="pt-3">
                            <h1 class="text-2xl font-extrabold text-slate-900 leading-tight">
                                {{ $restaurante->traduccion($idioma)->nombre }}
                            </h1>
                            @if ($restDesc = $restaurante->traduccion($idioma)->descripcion)
                                <p class="text-slate-600 text-sm mt-1 max-w-xl">
                                    {{ $restDesc }}
                                </p>
                            @endif

                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                @if ($estaAbierto)
                                    <span class="badge-open inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" aria-hidden="true"></span>
                                        Abierto ahora
                                    </span>
                                @else
                                    <span class="badge-closed inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                        <span class="w-2 h-2 rounded-full bg-rose-500" aria-hidden="true"></span>
                                        Cerrado
                                    </span>
                                @endif

                                @php
                                    $diaHoy = (int) now($restaurante->zona_horaria)->dayOfWeek;
                                    $turnosHoy = $horarios->where('dia_semana', $diaHoy)->where('cerrado', false);
                                @endphp
                                @if ($turnosHoy->isNotEmpty())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700" aria-label="Horario de atención hoy">
                                        <span aria-hidden="true">🕒</span>
                                        Hoy:
                                        @foreach ($turnosHoy as $t)
                                            {{ substr($t->abre, 0, 5) }} - {{ substr($t->cierra, 0, 5) }}@if (! $loop->last), @endif
                                        @endforeach
                                    </span>
                                @endif

                                @if ($mesa)
                                    <span class="badge-mesa inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800" aria-label="Ubicación asignada: Mesa {{ $mesa }}">
                                        Mesa {{ $mesa }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Language selector -->
                    @if (count($idiomasDisponibles) > 1)
                        <nav class="flex items-center gap-1.5 self-start sm:self-end pb-1" aria-label="Selector de idioma">
                            @foreach ($idiomasDisponibles as $langOption)
                                <a
                                    href="{{ request()->fullUrlWithQuery(['lang' => $langOption]) }}"
                                    class="min-h-[44px] min-w-[44px] px-3 py-2 rounded-lg text-xs font-bold uppercase transition-all inline-flex items-center justify-center focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2 focus-visible:outline-none {{ $idioma === $langOption ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
                                    aria-current="{{ $idioma === $langOption ? 'page' : 'false' }}"
                                    aria-label="Cambiar idioma a {{ strtoupper($langOption) }}"
                                >
                                    {{ $langOption }}
                                </a>
                            @endforeach
                        </nav>
                    @endif
                </div>

                <!-- Order eligibility warning banner -->
                @if (! $puedePedir)
                    <div role="status" aria-live="polite" class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs font-medium flex items-center gap-2">
                        <svg class="w-5 h-5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>
                            @if (! $restaurante->pedidos_activos)
                                Este restaurante no tiene pedidos en línea activos actualmente. Puedes consultar el menú digital libremente.
                            @elseif (! $estaAbierto)
                                El restaurante se encuentra cerrado en este momento. Los pedidos están pausados hasta su próxima apertura.
                            @elseif (! filled($restaurante->whatsapp_pedidos))
                                Servicio de pedidos temporalmente no disponible por WhatsApp.
                            @endif
                        </span>
                    </div>
                @endif
            </div>
        </header>

        <!-- Sticky Category Navigation Bar -->
        <nav class="sticky-nav bg-white/95 backdrop-blur-md border-b border-slate-200" aria-label="Categorías del menú">
            <div class="container mx-auto px-4 flex items-center gap-2 overflow-x-auto">
                @if ($destacados->isNotEmpty())
                    <a href="#destacados" class="nav-pill min-h-[44px] inline-flex items-center px-4 focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:outline-none"><span aria-hidden="true" class="mr-1">⭐</span> Destacados</a>
                @endif

                @foreach ($categorias as $cat)
                    @php
                        $catNom = $cat->traduccion($idioma)?->nombre ?? $cat->traduccion()?->nombre ?? 'Categoría';
                    @endphp
                    <a href="#cat-{{ $cat->id }}" class="nav-pill min-h-[44px] inline-flex items-center px-4 focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:outline-none">{{ $catNom }}</a>
                @endforeach

                @if ($horarios->isNotEmpty())
                    <a href="#horarios" class="nav-pill min-h-[44px] inline-flex items-center px-4 focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:outline-none"><span aria-hidden="true" class="mr-1">🕒</span> Horarios</a>
                @endif

                @if ($contactos->isNotEmpty())
                    <a href="#contacto" class="nav-pill min-h-[44px] inline-flex items-center px-4 focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:outline-none"><span aria-hidden="true" class="mr-1">📞</span> Contacto</a>
                @endif
            </div>
        </nav>

        <!-- Main Content -->
        <main class="container mx-auto px-4 py-6">

            <!-- Featured Section -->
            @if ($destacados->isNotEmpty())
                <section id="destacados" class="mb-10 scroll-mt-20">
                    <h2 class="text-xl font-extrabold text-slate-900 mb-4 flex items-center gap-2">
                        <span>⭐</span> Platillos Recomendados
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($destacados as $prod)
                            @include('menu.partials.dish-card', ['prod' => $prod, 'restaurante' => $restaurante, 'idioma' => $idioma, 'puedePedir' => $puedePedir])
                        @endforeach
                    </div>
                </section>
            @endif

            <!-- Categories and Dishes -->
            @foreach ($categorias as $cat)
                @php
                    $catNom = $cat->traduccion($idioma)?->nombre ?? $cat->traduccion()?->nombre ?? 'Categoría';
                    $catDesc = $cat->traduccion($idioma)?->descripcion ?? $cat->traduccion()?->descripcion;
                @endphp
                <section id="cat-{{ $cat->id }}" class="mb-10 scroll-mt-20">
                    <div class="mb-4">
                        <h2 class="text-xl font-extrabold text-slate-900">{{ $catNom }}</h2>
                        @if ($catDesc)
                            <p class="text-slate-600 text-xs mt-0.5">{{ $catDesc }}</p>
                        @endif
                    </div>

                    @if ($cat->productos->isEmpty())
                        <p class="text-slate-400 text-xs italic">No hay platillos disponibles en esta categoría.</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach ($cat->productos as $prod)
                                @include('menu.partials.dish-card', ['prod' => $prod, 'restaurante' => $restaurante, 'idioma' => $idioma, 'puedePedir' => $puedePedir])
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach

            <!-- Weekly Schedule Table -->
            @if ($horarios->isNotEmpty())
                <section id="horarios" class="mb-10 scroll-mt-20">
                    <div class="card bg-white rounded-2xl p-5 border border-slate-200">
                        <h2 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">
                            <span>🕒</span> Horarios de Atención
                        </h2>
                        @php
                            $dias = \App\Services\Catalogos::diasSemana();
                            $horariosPorDia = $horarios->groupBy('dia_semana');
                        @endphp
                        <div class="divide-y divide-slate-100 text-sm">
                            @foreach ($dias as $numDia => $nombreDia)
                                @php
                                    $turnos = $horariosPorDia->get($numDia);
                                @endphp
                                <div class="py-2 flex items-center justify-between">
                                    <span class="font-medium text-slate-700">{{ $nombreDia }}</span>
                                    <span class="text-slate-600">
                                        @if ($turnos && $turnos->where('cerrado', false)->isNotEmpty())
                                            @foreach ($turnos->where('cerrado', false) as $t)
                                                <span class="inline-block ml-1">
                                                    {{ substr($t->abre, 0, 5) }} - {{ substr($t->cierra, 0, 5) }}
                                                </span>
                                            @endforeach
                                        @else
                                            <span class="text-slate-400 font-normal">Cerrado</span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            <!-- Safe Contacts Section -->
            @if ($contactos->isNotEmpty())
                <section id="contacto" class="mb-10 scroll-mt-20">
                    <div class="card bg-white rounded-2xl p-5 border border-slate-200">
                        <h2 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">
                            <span>📞</span> Contacto y Ubicación
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                            @foreach ($contactos as $contacto)
                                @php
                                    $href = match ($contacto->tipo) {
                                        'telefono' => str_starts_with($contacto->valor, 'tel:') ? $contacto->valor : 'tel:' . preg_replace('/[^0-9+]/', '', $contacto->valor),
                                        'whatsapp' => 'https://wa.me/' . preg_replace('/[^0-9]/', '', $contacto->valor),
                                        'email' => str_starts_with($contacto->valor, 'mailto:') ? $contacto->valor : 'mailto:' . $contacto->valor,
                                        default => (str_starts_with($contacto->valor, 'http://') || str_starts_with($contacto->valor, 'https://')) ? $contacto->valor : 'https://' . $contacto->valor,
                                    };
                                @endphp
                                <a
                                    href="{{ $href }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="min-h-[44px] p-2.5 rounded-lg border border-slate-100 hover:border-slate-300 bg-slate-50 hover:bg-slate-100 transition-colors flex items-center justify-between text-slate-700 focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:outline-none"
                                >
                                    <span class="font-medium text-xs uppercase tracking-wider text-slate-500">{{ $contacto->etiqueta ?: $contacto->tipo }}</span>
                                    <span class="font-semibold text-slate-900 text-xs truncate max-w-[200px]">{{ $contacto->valor }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

        </main>

        <!-- Floating Cart Bar (Enabled only if $puedePedir) -->
        @if ($puedePedir)
            <button
                type="button"
                id="floatingCartBar"
                class="floating-cart-bar w-full focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2 focus-visible:outline-none"
                style="display: none;"
                onclick="openCartModal()"
                aria-haspopup="dialog"
                aria-expanded="false"
                aria-controls="cartModalBackdrop"
                aria-label="Ver mi pedido"
            >
                <div class="flex items-center gap-3">
                    <span id="cartCountBadge" class="bg-emerald-700 text-white font-extrabold text-xs px-2.5 py-1 rounded-full min-w-[24px] text-center" aria-live="polite">
                        0
                    </span>
                    <span class="font-bold text-sm">Ver mi pedido</span>
                </div>
                <div class="font-extrabold text-base">
                    <span id="cartTotalSummary" aria-live="polite">0.00</span> {{ $restaurante->moneda }}
                </div>
            </button>

            <!-- Cart Drawer Modal -->
            <div
                id="cartModalBackdrop"
                class="cart-modal-backdrop"
                onclick="closeCartModal(event)"
                role="dialog"
                aria-modal="true"
                aria-labelledby="cartDrawerTitle"
            >
                <div class="cart-drawer" onclick="event.stopPropagation()">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2">
                            <span class="text-xl" aria-hidden="true">🛒</span>
                            <h3 id="cartDrawerTitle" class="text-lg font-bold text-slate-900">Tu Pedido</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                id="btnVaciarCarrito"
                                onclick="cartConfirmarVaciar()"
                                class="text-xs font-semibold text-rose-600 hover:text-rose-800 hover:bg-rose-50 px-2.5 py-1.5 rounded-lg transition-colors focus-visible:ring-2 focus-visible:ring-rose-600 focus-visible:outline-none"
                                aria-label="Vaciar todo el pedido"
                            >
                                Vaciar pedido
                            </button>
                            <button
                                type="button"
                                id="btnCloseCartModal"
                                onclick="closeCartModal()"
                                class="w-11 h-11 min-w-[44px] min-h-[44px] rounded-full text-slate-600 hover:text-slate-900 hover:bg-slate-100 flex items-center justify-center transition-colors focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:outline-none"
                                aria-label="Cerrar pedido"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Inline Confirmation Banner for Vaciar Pedido (initially hidden) -->
                    <div id="vaciarConfirmBanner" class="hidden mb-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-900 text-xs sm:text-sm">
                        <p class="font-semibold mb-2">¿Seguro que deseas vaciar todos los platillos de tu pedido?</p>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                onclick="cartEjecutarVaciar()"
                                class="min-h-[44px] px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-lg transition-colors focus-visible:ring-2 focus-visible:ring-rose-600 focus-visible:outline-none"
                            >
                                Sí, vaciar
                            </button>
                            <button
                                type="button"
                                onclick="cartCancelarVaciar()"
                                class="min-h-[44px] px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold rounded-lg transition-colors focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:outline-none"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>

                    <!-- Cart items list -->
                    <div id="cartItemsContainer" class="divide-y divide-slate-100 mb-4 max-h-60 overflow-y-auto" role="region" aria-label="Lista de platillos del pedido">
                        <!-- Rendered dynamically by JS -->
                    </div>

                    <!-- Table & Notes Form -->
                    <div class="space-y-3 pt-3 border-t border-slate-100">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1" for="inputMesa">
                                Mesa / Ubicación:
                            </label>
                            <input
                                type="text"
                                id="inputMesa"
                                value="{{ $mesa ?? '' }}"
                                placeholder="Ej. Mesa 4 o Para llevar"
                                aria-label="Mesa o ubicación de entrega"
                                class="w-full text-sm px-3 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-900"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1" for="inputNotasGenerales">
                                Nota general para la cocina:
                            </label>
                            <textarea
                                id="inputNotasGenerales"
                                rows="2"
                                placeholder="Indicaciones especiales, cubiertos, etc."
                                aria-label="Nota general para la cocina"
                                class="w-full text-sm px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-900"
                            ></textarea>
                        </div>

                        <!-- Summary -->
                        <div class="flex items-center justify-between text-base font-extrabold text-slate-900 pt-2">
                            <span>Total:</span>
                            <span><span id="modalCartTotal" aria-live="polite">0.00</span> {{ $restaurante->moneda }}</span>
                        </div>

                        <!-- WhatsApp Submit Button -->
                        <button
                            type="button"
                            id="btnEnviarWhatsApp"
                            onclick="enviarPedidoWhatsApp()"
                            class="w-full min-h-[48px] py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl flex items-center justify-center gap-2 shadow-lg transition-colors focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766 0-3.187-2.59-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.06-2.155-.544-1.631-.674-2.732-2.317-2.825-2.433-.092-.116-.694-.925-.694-1.765 0-.84.44-1.254.597-1.428.156-.173.344-.216.459-.216.115 0 .23.001.33.006.106.005.25.04.39.376.144.348.49 1.198.533 1.285.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.353.101.174.45 1.743 1.968 2.091.246.056.442.062.607-.021.165-.084.536-.622.68-.838.144-.217.288-.174.483-.102.195.072 1.241.586 1.455.693.214.108.358.161.41.25.053.089.053.518-.091.923z"/>
                            </svg>
                            <span>Enviar Pedido por WhatsApp</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Client-Side Cart Script (Isolated under localStorage menuonl_cart_{slug}) -->
        <script>
            (function() {
                const NOMBRE = @js($restaurante->nombre);
                const CART_KEY = @js('menuonl_cart_'.$restaurante->slug);
                const MONEDA = @js($restaurante->moneda);
                const WHATSAPP_NUM = @js(preg_replace('/[^0-9]/', '', (string) $restaurante->whatsapp_pedidos));
                const PUEDE_PEDIR = @js((bool) $puedePedir);
                let toastTimeout = null;

                function escapeHtml(str) {
                    if (!str) return '';
                    return String(str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                function mostrarToast(mensaje) {
                    const toast = document.getElementById('cartToast');
                    const toastMsg = document.getElementById('cartToastMessage');
                    if (!toast || !toastMsg) return;
                    toastMsg.textContent = mensaje;
                    toast.classList.add('visible');
                    if (toastTimeout) clearTimeout(toastTimeout);
                    toastTimeout = setTimeout(function() {
                        toast.classList.remove('visible');
                    }, 2500);
                }
                window.mostrarNotificacionCarrito = mostrarToast;

                function loadCart() {
                    try {
                        const raw = localStorage.getItem(CART_KEY);
                        return raw ? JSON.parse(raw) : [];
                    } catch (e) {
                        return [];
                    }
                }

                function saveCart(cart) {
                    try {
                        localStorage.setItem(CART_KEY, JSON.stringify(cart));
                    } catch (e) {}
                    renderCart();
                }

                window.cartAddItem = function(item) {
                    if (!PUEDE_PEDIR) return;
                    const cart = loadCart();
                    const existingIndex = cart.findIndex(c => c.producto_id === item.producto_id && c.variante_id === item.variante_id);

                    if (existingIndex > -1) {
                        cart[existingIndex].cantidad += (item.cantidad || 1);
                    } else {
                        cart.push({
                            producto_id: item.producto_id,
                            variante_id: item.variante_id || null,
                            nombre: item.nombre,
                            variante_nombre: item.variante_nombre || null,
                            precio: parseFloat(item.precio),
                            cantidad: item.cantidad || 1,
                            notas: item.notas || ''
                        });
                    }
                    saveCart(cart);
                    mostrarToast('Agregaste ' + item.nombre + (item.variante_nombre ? ' (' + item.variante_nombre + ')' : '') + ' al pedido');
                };

                window.cartUpdateQty = function(index, delta) {
                    const cart = loadCart();
                    if (!cart[index]) return;
                    cart[index].cantidad += delta;
                    if (cart[index].cantidad <= 0) {
                        cart.splice(index, 1);
                    }
                    saveCart(cart);
                };

                window.cartUpdateItemNotes = function(index, notes) {
                    const cart = loadCart();
                    if (!cart[index]) return;
                    cart[index].notas = notes;
                    try {
                        localStorage.setItem(CART_KEY, JSON.stringify(cart));
                    } catch (e) {}
                };

                window.cartConfirmarVaciar = function() {
                    const banner = document.getElementById('vaciarConfirmBanner');
                    if (banner) banner.classList.remove('hidden');
                };

                window.cartCancelarVaciar = function() {
                    const banner = document.getElementById('vaciarConfirmBanner');
                    if (banner) banner.classList.add('hidden');
                };

                window.cartEjecutarVaciar = function() {
                    saveCart([]);
                    cartCancelarVaciar();
                    mostrarToast('Se vació tu pedido.');
                };

                window.openCartModal = function() {
                    const modal = document.getElementById('cartModalBackdrop');
                    if (modal) modal.classList.add('open');
                    const bar = document.getElementById('floatingCartBar');
                    if (bar) bar.setAttribute('aria-expanded', 'true');
                    document.body.style.overflow = 'hidden';
                    const closeBtn = document.getElementById('btnCloseCartModal');
                    if (closeBtn) closeBtn.focus();
                };

                window.closeCartModal = function(e) {
                    if (e && e.target && e.target !== document.getElementById('cartModalBackdrop')) {
                        return;
                    }
                    const modal = document.getElementById('cartModalBackdrop');
                    if (modal) modal.classList.remove('open');
                    const bar = document.getElementById('floatingCartBar');
                    if (bar) bar.setAttribute('aria-expanded', 'false');
                    document.body.style.overflow = '';
                    cartCancelarVaciar();
                    if (bar && bar.style.display !== 'none' && !e) {
                        bar.focus();
                    }
                };

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' || e.key === 'Esc') {
                        const modal = document.getElementById('cartModalBackdrop');
                        if (modal && modal.classList.contains('open')) {
                            closeCartModal();
                        }
                    }
                });

                window.enviarPedidoWhatsApp = function() {
                    const cart = loadCart();
                    if (cart.length === 0) {
                        alert('Tu carrito está vacío.');
                        return;
                    }

                    const mesaInput = document.getElementById('inputMesa');
                    const mesaVal = mesaInput ? mesaInput.value.trim() : '';
                    const mesaText = mesaVal ? mesaVal : 'Para llevar / Sin mesa';

                    const notasGeneralesInput = document.getElementById('inputNotasGenerales');
                    const notasGenerales = notasGeneralesInput ? notasGeneralesInput.value.trim() : '';

                    let total = 0;
                    let itemsText = '';

                    cart.forEach(item => {
                        const itemSub = item.precio * item.cantidad;
                        total += itemSub;
                        const vName = item.variante_nombre ? ` (${item.variante_nombre})` : '';
                        itemsText += `• ${item.cantidad}x ${item.nombre}${vName} - $${itemSub.toFixed(2)} ${MONEDA}\n`;
                        if (item.notas && item.notas.trim()) {
                            itemsText += `  _Nota: ${item.notas.trim()}_\n`;
                        }
                    });

                    let msg = `¡Hola! Quiero hacer el siguiente pedido desde el menú digital de *${NOMBRE}*:\n\n`;
                    msg += `*Ubicación/Mesa:* ${mesaText}\n\n`;
                    msg += `*Detalle del pedido:*\n${itemsText}\n`;
                    if (notasGenerales) {
                        msg += `*Indicaciones:* ${notasGenerales}\n\n`;
                    }
                    msg += `*Total estimado:* $${total.toFixed(2)} ${MONEDA}`;

                    const url = `https://wa.me/${WHATSAPP_NUM}?text=${encodeURIComponent(msg)}`;
                    window.open(url, '_blank', 'noopener,noreferrer');
                };

                function renderCart() {
                    if (!PUEDE_PEDIR) return;
                    const cart = loadCart();
                    const bar = document.getElementById('floatingCartBar');
                    const badge = document.getElementById('cartCountBadge');
                    const barTotal = document.getElementById('cartTotalSummary');
                    const modalTotal = document.getElementById('modalCartTotal');
                    const itemsContainer = document.getElementById('cartItemsContainer');
                    const btnVaciar = document.getElementById('btnVaciarCarrito');
                    const btnEnviar = document.getElementById('btnEnviarWhatsApp');

                    let totalQty = 0;
                    let totalPrice = 0;

                    cart.forEach(item => {
                        totalQty += item.cantidad;
                        totalPrice += (item.precio * item.cantidad);
                    });

                    if (badge) badge.textContent = totalQty;
                    if (barTotal) barTotal.textContent = totalPrice.toFixed(2);
                    if (modalTotal) modalTotal.textContent = totalPrice.toFixed(2);

                    if (bar) {
                        bar.style.display = totalQty > 0 ? 'flex' : 'none';
                        bar.setAttribute('aria-label', `Ver mi pedido: ${totalQty} artículos, total $${totalPrice.toFixed(2)} ${MONEDA}`);
                    }

                    if (btnVaciar) {
                        btnVaciar.style.display = totalQty > 0 ? 'inline-block' : 'none';
                    }

                    if (btnEnviar) {
                        btnEnviar.disabled = (totalQty === 0);
                        btnEnviar.setAttribute('aria-disabled', totalQty === 0 ? 'true' : 'false');
                    }

                    if (itemsContainer) {
                        if (cart.length === 0) {
                            itemsContainer.innerHTML = '<p class="py-6 text-center text-slate-400 text-sm">Tu carrito está vacío.</p>';
                        } else {
                            let html = '';
                            cart.forEach((item, idx) => {
                                const cantidad = parseInt(item.cantidad, 10) || 0;
                                const precio = Number(item.precio) || 0;
                                const vName = item.variante_nombre ? `<span class="text-xs text-slate-500 font-normal">(${escapeHtml(item.variante_nombre)})</span>` : '';
                                const itemNombreEsc = escapeHtml(item.nombre);
                                html += `
                                    <div class="py-3">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="text-sm font-bold text-slate-900">${itemNombreEsc} ${vName}</h4>
                                                <span class="text-xs font-semibold text-slate-600">$${(precio * cantidad).toFixed(2)} ${MONEDA}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    onclick="cartUpdateQty(${idx}, -1)"
                                                    aria-label="Disminuir cantidad de ${itemNombreEsc}"
                                                    class="w-11 h-11 min-w-[44px] min-h-[44px] rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-extrabold flex items-center justify-center text-base focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:outline-none"
                                                >
                                                    <span aria-hidden="true">-</span>
                                                </button>
                                                <span class="text-sm font-extrabold text-slate-900 px-2 min-w-[24px] text-center" aria-label="Cantidad: ${cantidad}">${cantidad}</span>
                                                <button
                                                    type="button"
                                                    onclick="cartUpdateQty(${idx}, 1)"
                                                    aria-label="Aumentar cantidad de ${itemNombreEsc}"
                                                    class="w-11 h-11 min-w-[44px] min-h-[44px] rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-extrabold flex items-center justify-center text-base focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:outline-none"
                                                >
                                                    <span aria-hidden="true">+</span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <input
                                                type="text"
                                                placeholder="Nota (ej. sin picante)"
                                                aria-label="Instrucciones especiales para ${itemNombreEsc}"
                                                value="${item.notas ? escapeHtml(item.notas) : ''}"
                                                oninput="cartUpdateItemNotes(${idx}, this.value)"
                                                onchange="cartUpdateItemNotes(${idx}, this.value)"
                                                class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-900"
                                            />
                                        </div>
                                    </div>
                                `;
                            });
                            itemsContainer.innerHTML = html;
                        }
                    }
                }

                document.addEventListener('DOMContentLoaded', renderCart);
            })();
        </script>
    </body>
</html>
