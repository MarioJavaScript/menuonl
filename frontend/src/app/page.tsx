'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { ThemeSwitcher } from '@/components/ThemeSwitcher';
import { IconUtensils, IconWhatsApp, IconShoppingBag, IconChevronRight } from '@/components/Icons';

export default function HomePage() {
  const router = useRouter();
  const [slugInput, setSlugInput] = useState('');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const cleanSlug = slugInput.trim().toLowerCase();
    if (cleanSlug) {
      router.push(`/m/${encodeURIComponent(cleanSlug)}`);
    }
  };

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-zinc-100 flex flex-col justify-between transition-colors">
      {/* Top Navbar */}
      <header className="w-full border-b border-slate-200/80 dark:border-zinc-800/80 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md">
        <div className="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <div className="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-black text-sm shadow-sm">
              M
            </div>
            <div>
              <span className="font-extrabold text-base tracking-tight">MENUONL</span>
              <span className="ml-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                Next.js 15
              </span>
            </div>
          </div>

          <ThemeSwitcher />
        </div>
      </header>

      {/* Main Hero */}
      <main className="max-w-3xl mx-auto px-4 sm:px-6 py-12 sm:py-20 text-center space-y-8 flex-1 flex flex-col justify-center">
        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 text-xs font-semibold mx-auto">
          <IconShoppingBag size={14} />
          <span>Menú Público Desacoplado & Pedidos por WhatsApp</span>
        </div>

        <div className="space-y-3">
          <h1 className="text-3xl sm:text-5xl font-black text-slate-900 dark:text-zinc-50 tracking-tight leading-tight">
            Menú Digital Rápido, Responsivo y Moderno
          </h1>
          <p className="text-sm sm:text-base text-slate-600 dark:text-zinc-400 max-w-xl mx-auto leading-relaxed">
            Explora cartas digitales interactivas, selecciona platillos y variantes, y envía tus pedidos formateados directamente a WhatsApp.
          </p>
        </div>

        {/* Slug input box */}
        <div className="max-w-md mx-auto w-full">
          <form
            onSubmit={handleSubmit}
            className="flex flex-col sm:flex-row gap-2 bg-white dark:bg-zinc-900 p-2 rounded-2xl shadow-lg border border-slate-200 dark:border-zinc-800"
          >
            <div className="flex-1 flex items-center px-3 gap-2">
              <span className="text-xs text-slate-400 select-none">/m/</span>
              <input
                type="text"
                value={slugInput}
                onChange={(e) => setSlugInput(e.target.value)}
                placeholder="slug-del-restaurante"
                aria-label="Slug del restaurante"
                className="w-full text-sm bg-transparent focus:outline-none text-slate-900 dark:text-zinc-100 placeholder-slate-400"
              />
            </div>
            <button
              type="submit"
              disabled={!slugInput.trim()}
              className="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold text-sm rounded-xl transition-all flex items-center justify-center gap-1.5 min-h-[44px]"
            >
              <span>Ver Menú</span>
              <IconChevronRight size={16} />
            </button>
          </form>
        </div>

        {/* Demo shortcuts */}
        <div className="pt-4 space-y-3">
          <span className="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-zinc-500">
            O prueba un menú de demostración:
          </span>
          <div className="flex flex-wrap items-center justify-center gap-2.5">
            <Link
              href="/m/taqueria-la-autentica"
              className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-white dark:bg-zinc-900 hover:bg-slate-100 dark:hover:bg-zinc-800 border border-slate-200 dark:border-zinc-800 text-slate-800 dark:text-zinc-200 shadow-xs transition-all min-h-[44px]"
            >
              <IconUtensils size={15} className="text-emerald-600 dark:text-emerald-400" />
              <span>Taquería La Auténtica</span>
            </Link>
            <Link
              href="/m/taqueria-la-autentica?mesa=Mesa-5"
              className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-white dark:bg-zinc-900 hover:bg-slate-100 dark:hover:bg-zinc-800 border border-slate-200 dark:border-zinc-800 text-slate-800 dark:text-zinc-200 shadow-xs transition-all min-h-[44px]"
            >
              <IconWhatsApp size={15} className="text-emerald-600" />
              <span>Con Mesa Pre-asignada (?mesa=Mesa-5)</span>
            </Link>
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="w-full border-t border-slate-200 dark:border-zinc-800 py-6 text-center text-xs text-slate-500 dark:text-zinc-500">
        MENUONL Frontend • Next.js 15 App Router & Tailwind CSS v4
      </footer>
    </div>
  );
}
