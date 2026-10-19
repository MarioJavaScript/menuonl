'use client';

import React, { useEffect, useState } from 'react';
import { IconSun, IconMoon, IconMonitor } from './Icons';

type ThemeMode = 'light' | 'dark' | 'system';

export function ThemeSwitcher() {
  const [theme, setTheme] = useState<ThemeMode>('system');
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    try {
      const stored = localStorage.getItem('menuonl_theme') as ThemeMode | null;
      if (stored === 'light' || stored === 'dark' || stored === 'system') {
        setTheme(stored);
      }
    } catch {
      // Ignored
    }
    setMounted(true);
  }, []);

  const applyTheme = (mode: ThemeMode) => {
    setTheme(mode);
    try {
      localStorage.setItem('menuonl_theme', mode);
    } catch {
      // Ignored
    }

    const root = document.documentElement;
    if (mode === 'dark') {
      root.classList.add('dark');
    } else if (mode === 'light') {
      root.classList.remove('dark');
    } else {
      // System
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (prefersDark) {
        root.classList.add('dark');
      } else {
        root.classList.remove('dark');
      }
    }
  };

  useEffect(() => {
    if (!mounted || theme !== 'system') return;

    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const handleChange = (e: MediaQueryListEvent) => {
      if (e.matches) {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
    };

    mediaQuery.addEventListener('change', handleChange);
    return () => mediaQuery.removeEventListener('change', handleChange);
  }, [mounted, theme]);

  if (!mounted) {
    return (
      <div className="inline-flex items-center p-1 rounded-full bg-slate-200/60 dark:bg-zinc-800 border border-slate-300/60 dark:border-zinc-700/60 h-9 w-[184px] animate-pulse" />
    );
  }

  const options: { mode: ThemeMode; label: string; icon: React.ReactNode }[] = [
    { mode: 'light', label: 'Claro', icon: <IconSun size={15} /> },
    { mode: 'dark', label: 'Oscuro', icon: <IconMoon size={15} /> },
    { mode: 'system', label: 'Sistema', icon: <IconMonitor size={15} /> },
  ];

  return (
    <div
      role="group"
      aria-label="Selector de apariencia"
      className="inline-flex items-center p-1 rounded-full bg-slate-200/70 dark:bg-zinc-800/80 border border-slate-300 dark:border-zinc-700 shadow-xs"
    >
      {options.map((opt) => {
        const isActive = theme === opt.mode;
        return (
          <button
            key={opt.mode}
            type="button"
            onClick={() => applyTheme(opt.mode)}
            aria-pressed={isActive}
            className={`flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded-full transition-all duration-200 min-h-[32px] ${
              isActive
                ? 'bg-white text-slate-900 shadow-sm dark:bg-zinc-700 dark:text-zinc-100 font-semibold'
                : 'text-slate-600 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-zinc-200'
            }`}
          >
            {opt.icon}
            <span>{opt.label}</span>
          </button>
        );
      })}
    </div>
  );
}
