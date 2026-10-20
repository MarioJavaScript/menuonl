'use client';

import React from 'react';
import { RestaurantData } from '@/types/menu';
import { CartProvider } from '@/context/CartContext';
import { HeroHeader } from './HeroHeader';
import { CategoryNav } from './CategoryNav';
import { DishCard } from './DishCard';
import { CartFloatingBar } from './CartFloatingBar';
import { CartDrawer } from './CartDrawer';
import { ThemeSwitcher } from './ThemeSwitcher';
import { IconStar } from './Icons';

interface PublicMenuClientProps {
  restaurant: RestaurantData;
  initialMesa?: string | null;
}

export function PublicMenuClient({
  restaurant,
  initialMesa,
}: PublicMenuClientProps) {
  const table = initialMesa || restaurant.mesa;
  const currency = restaurant.moneda || 'MXN';

  return (
    <CartProvider
      slug={restaurant.slug}
      currency={currency}
      initialTable={table}
    >
      <div className="min-h-screen bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-zinc-100 flex flex-col transition-colors selection:bg-emerald-500 selection:text-white">
        {/* Restaurant Header */}
        <HeroHeader restaurant={restaurant} activeTable={table} />

        {/* Sticky Category Navigation */}
        <CategoryNav
          categorias={restaurant.categorias}
          destacados={restaurant.destacados}
        />

        {/* Catalog Main Content */}
        <main className="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-10 sm:space-y-12 pb-28">
          {/* Featured Section if available */}
          {restaurant.destacados && restaurant.destacados.length > 0 && (
            <section
              id="seccion-destacados"
              className="scroll-mt-24 space-y-3.5"
              aria-labelledby="heading-destacados"
            >
              <div className="flex items-center gap-2 border-b border-slate-200/80 dark:border-zinc-800/80 pb-2.5">
                <div className="p-1.5 rounded-lg bg-amber-500 text-white dark:bg-amber-500 dark:text-zinc-950">
                  <IconStar size={18} className="fill-current" />
                </div>
                <div>
                  <h2
                    id="heading-destacados"
                    className="text-lg sm:text-xl font-extrabold text-slate-900 dark:text-zinc-50 tracking-tight"
                  >
                    Platillos Destacados
                  </h2>
                  <p className="text-xs text-slate-500 dark:text-zinc-400">
                    Nuestras recomendaciones especiales de la casa
                  </p>
                </div>
              </div>

              {/* Grid: 1 col on mobile (<768px), 2 cols on tablet & desktop (>=768px) */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3.5 sm:gap-4">
                {restaurant.destacados.map((dish) => (
                  <DishCard key={`destacado-${dish.id}`} dish={dish} currency={currency} />
                ))}
              </div>
            </section>
          )}

          {/* Categories Sections */}
          {restaurant.categorias.length === 0 ? (
            <div className="text-center py-16 bg-white dark:bg-zinc-900 rounded-3xl border border-slate-200 dark:border-zinc-800 p-8 shadow-xs">
              <h3 className="font-bold text-base text-slate-800 dark:text-zinc-200">
                Menú en actualización
              </h3>
              <p className="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1">
                Próximamente estaremos agregando platillos a nuestro menú digital.
              </p>
            </div>
          ) : (
            restaurant.categorias.map((cat) => (
              <section
                key={cat.id}
                id={`categoria-${cat.id}`}
                className="scroll-mt-24 space-y-3.5"
                aria-labelledby={`heading-cat-${cat.id}`}
              >
                <div className="flex flex-col gap-0.5 border-b border-slate-200/80 dark:border-zinc-800/80 pb-2.5">
                  <div className="flex items-center justify-between">
                    <h2
                      id={`heading-cat-${cat.id}`}
                      className="text-lg sm:text-xl font-extrabold text-slate-900 dark:text-zinc-50 tracking-tight"
                    >
                      {cat.nombre}
                    </h2>
                    <span className="text-xs font-semibold text-slate-500 dark:text-zinc-400">
                      {cat.platillos.length}{' '}
                      {cat.platillos.length === 1 ? 'platillo' : 'platillos'}
                    </span>
                  </div>

                  {cat.descripcion && (
                    <p className="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed">
                      {cat.descripcion}
                    </p>
                  )}
                </div>

                {/* Grid: 1 col on mobile (<768px), 2 cols on tablet & desktop (>=768px) */}
                {cat.platillos.length === 0 ? (
                  <p className="text-xs text-slate-400 italic py-2">
                    No hay productos disponibles en esta categoría.
                  </p>
                ) : (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3.5 sm:gap-4">
                    {cat.platillos.map((dish) => (
                      <DishCard key={dish.id} dish={dish} currency={currency} />
                    ))}
                  </div>
                )}
              </section>
            ))
          )}
        </main>

        {/* Floating Cart Bar (appears when items in cart) */}
        <CartFloatingBar />

        {/* Cart Drawer */}
        <CartDrawer restaurant={restaurant} />

        {/* Footer with Theme Switcher & Credits */}
        <footer className="w-full border-t border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 py-8 px-4 text-center mt-auto transition-colors">
          <div className="max-w-5xl mx-auto flex flex-col items-center gap-4">
            {/* Theme Switcher */}
            <div className="flex flex-col items-center gap-1.5">
              <span className="text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:text-zinc-400">
                Apariencia
              </span>
              <ThemeSwitcher />
            </div>

            {/* Restaurant Credits */}
            <div className="text-xs text-slate-500 dark:text-zinc-400 space-y-1">
              <p className="font-semibold text-slate-700 dark:text-zinc-300">
                {restaurant.nombre}
              </p>
              <p>Menú Digital interactivo impulsado por MENUONL</p>
            </div>
          </div>
        </footer>
      </div>
    </CartProvider>
  );
}
