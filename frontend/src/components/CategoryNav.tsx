'use client';

import React, { useEffect, useRef, useState } from 'react';
import { CategoriaItem, DishItem } from '@/types/menu';
import { IconStar } from './Icons';

interface CategoryNavProps {
  categorias: CategoriaItem[];
  destacados?: DishItem[];
}

export function CategoryNav({ categorias, destacados = [] }: CategoryNavProps) {
  const [activeId, setActiveId] = useState<string>(
    destacados.length > 0 ? 'seccion-destacados' : (categorias[0] ? `categoria-${categorias[0].id}` : '')
  );
  const navContainerRef = useRef<HTMLDivElement>(null);

  // Track scroll position to update active pill
  useEffect(() => {
    const sectionIds: string[] = [];
    if (destacados.length > 0) {
      sectionIds.push('seccion-destacados');
    }
    for (const cat of categorias) {
      sectionIds.push(`categoria-${cat.id}`);
    }

    const handleScroll = () => {
      const scrollPosition = window.scrollY + 120; // Offset for sticky header and nav

      for (let i = sectionIds.length - 1; i >= 0; i--) {
        const id = sectionIds[i];
        const el = document.getElementById(id);
        if (el) {
          const top = el.offsetTop;
          if (scrollPosition >= top) {
            setActiveId(id);
            break;
          }
        }
      }
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();

    return () => window.removeEventListener('scroll', handleScroll);
  }, [categorias, destacados]);

  // Keep active pill scrolled into view horizontally within the nav bar
  useEffect(() => {
    if (!navContainerRef.current || !activeId) return;
    const activeButton = navContainerRef.current.querySelector(
      `[data-nav-id="${activeId}"]`
    ) as HTMLElement | null;

    if (activeButton) {
      const container = navContainerRef.current;
      const buttonLeft = activeButton.offsetLeft;
      const buttonWidth = activeButton.offsetWidth;
      const containerWidth = container.offsetWidth;
      const targetScroll = buttonLeft - containerWidth / 2 + buttonWidth / 2;

      container.scrollTo({
        left: Math.max(0, targetScroll),
        behavior: 'smooth',
      });
    }
  }, [activeId]);

  const scrollToSection = (id: string) => {
    setActiveId(id);
    const element = document.getElementById(id);
    if (element) {
      const headerOffset = 70;
      const elementPosition = element.getBoundingClientRect().top;
      const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

      window.scrollTo({
        top: offsetPosition,
        behavior: 'smooth',
      });
    }
  };

  return (
    <nav
      aria-label="Navegación de categorías"
      className="sticky top-0 z-30 w-full bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md border-b border-slate-200/80 dark:border-zinc-800/80 shadow-xs transition-colors"
    >
      <div className="max-w-5xl mx-auto px-4 sm:px-6">
        <div
          ref={navContainerRef}
          className="flex items-center gap-2 overflow-x-auto py-3 no-scrollbar scroll-smooth"
          style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
        >
          {/* Featured pill if present */}
          {destacados.length > 0 && (
            <button
              type="button"
              data-nav-id="seccion-destacados"
              onClick={() => scrollToSection('seccion-destacados')}
              className={`inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs sm:text-sm font-medium whitespace-nowrap transition-all duration-200 min-h-[40px] shrink-0 ${
                activeId === 'seccion-destacados'
                  ? 'bg-amber-500 text-white shadow-md dark:bg-amber-500 dark:text-zinc-950 font-semibold scale-[1.02]'
                  : 'bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-700'
              }`}
            >
              <IconStar size={14} className={activeId === 'seccion-destacados' ? 'fill-current' : ''} />
              <span>Destacados</span>
              <span
                className={`ml-0.5 px-1.5 py-0.2 rounded-full text-[10px] font-bold ${
                  activeId === 'seccion-destacados'
                    ? 'bg-black/20 text-white dark:text-zinc-950'
                    : 'bg-slate-200 dark:bg-zinc-700 text-slate-700 dark:text-zinc-300'
                }`}
              >
                {destacados.length}
              </span>
            </button>
          )}

          {/* Categories */}
          {categorias.map((cat) => {
            const id = `categoria-${cat.id}`;
            const isActive = activeId === id;
            const count = cat.platillos.length;

            return (
              <button
                key={cat.id}
                type="button"
                data-nav-id={id}
                onClick={() => scrollToSection(id)}
                className={`inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs sm:text-sm font-medium whitespace-nowrap transition-all duration-200 min-h-[40px] shrink-0 ${
                  isActive
                    ? 'bg-slate-900 text-white shadow-md dark:bg-zinc-100 dark:text-zinc-900 font-semibold scale-[1.02]'
                    : 'bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-700'
                }`}
              >
                <span>{cat.nombre}</span>
                <span
                  className={`px-1.5 py-0.2 rounded-full text-[10px] font-bold ${
                    isActive
                      ? 'bg-white/20 text-white dark:bg-black/10 dark:text-zinc-900'
                      : 'bg-slate-200 dark:bg-zinc-700 text-slate-700 dark:text-zinc-300'
                  }`}
                >
                  {count}
                </span>
              </button>
            );
          })}
        </div>
      </div>
    </nav>
  );
}
