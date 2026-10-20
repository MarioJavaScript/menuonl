'use client';

import React from 'react';
import Link from 'next/link';
import { ThemeSwitcher } from './ThemeSwitcher';
import { IconAlertCircle, IconUtensils } from './Icons';

interface MenuErrorViewProps {
  status: number;
  message: string;
  slug: string;
}

export function MenuErrorView({ status, message, slug }: MenuErrorViewProps) {
  const isNotFound = status === 404;

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-zinc-100 flex flex-col justify-between p-4 transition-colors">
      <header className="max-w-md w-full mx-auto pt-6 flex items-center justify-between">
        <Link
          href="/"
          className="flex items-center gap-2 font-bold text-slate-800 dark:text-zinc-200 text-sm"
        >
          <div className="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-black text-xs">
            M
          </div>
          <span>MENUONL</span>
        </Link>
        <ThemeSwitcher />
      </header>

      <main className="max-w-md w-full mx-auto my-auto py-12 text-center">
        <div className="w-20 h-20 mx-auto rounded-3xl bg-slate-100 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 flex items-center justify-center text-slate-400 dark:text-zinc-500 shadow-md mb-6">
          {isNotFound ? (
            <IconUtensils size={36} className="text-slate-400 dark:text-zinc-500" />
          ) : (
            <IconAlertCircle size={36} className="text-amber-500" />
          )}
        </div>

        <span className="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-slate-200 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300 mb-3">
          {isNotFound ? '404 • No disponible' : `Error ${status || 'de red'}`}
        </span>

        <h1 className="text-xl sm:text-2xl font-black text-slate-900 dark:text-zinc-50 tracking-tight">
          {isNotFound ? 'Restaurante no encontrado' : 'Error de conexión'}
        </h1>

        <p className="mt-2 text-sm text-slate-600 dark:text-zinc-400 max-w-sm mx-auto leading-relaxed">
          {message ||
            (isNotFound
              ? `El menú correspondiente a "${slug}" no existe o se encuentra inactivo.`
              : 'No pudimos conectar con el servidor de la API de MENUONL.')}
        </p>

        <div className="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
          <button
            type="button"
            onClick={() => window.location.reload()}
            className="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm transition-all shadow-md min-h-[44px]"
          >
            Reintentar
          </button>
          <Link
            href="/"
            className="w-full sm:w-auto px-6 py-2.5 rounded-xl border border-slate-300 dark:border-zinc-700 hover:bg-slate-100 dark:hover:bg-zinc-800 text-slate-700 dark:text-zinc-300 font-semibold text-sm transition-all min-h-[44px] flex items-center justify-center"
          >
            Volver al inicio
          </Link>
        </div>
      </main>

      <footer className="max-w-md w-full mx-auto pb-6 text-center text-xs text-slate-400 dark:text-zinc-600">
        MENUONL • Menús Digitales para Restaurantes
      </footer>
    </div>
  );
}
