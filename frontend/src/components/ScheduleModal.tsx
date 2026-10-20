'use client';

import React, { useEffect } from 'react';
import { HorarioItem } from '@/types/menu';
import { IconClock, IconClose } from './Icons';

interface ScheduleModalProps {
  isOpen: boolean;
  onClose: () => void;
  horarios: HorarioItem[];
  restaurantName: string;
}

export function ScheduleModal({
  isOpen,
  onClose,
  horarios,
  restaurantName,
}: ScheduleModalProps) {
  useEffect(() => {
    if (!isOpen) return;

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };

    window.addEventListener('keydown', handleKeyDown);
    document.body.style.overflow = 'hidden';

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = '';
    };
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  // Today's day of week: 0 = Sunday, 1 = Monday, etc.
  const todayDayOfWeek = new Date().getDay();

  // Group or sort schedules by day 1..6, 0 (Lunes to Domingo) or 0..6
  const sortedHorarios = [...horarios].sort((a, b) => {
    // Put Lunes (1) first, up to Domingo (0) last
    const aOrder = a.dia_semana === 0 ? 7 : a.dia_semana;
    const bOrder = b.dia_semana === 0 ? 7 : b.dia_semana;
    return aOrder - bOrder;
  });

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="schedule-modal-title"
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in duration-200"
      onClick={onClose}
    >
      <div
        className="w-full max-w-md bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-zinc-800 overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-slate-100 dark:border-zinc-800">
          <div className="flex items-center gap-2">
            <div className="p-2 rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-200">
              <IconClock size={18} />
            </div>
            <div>
              <h3
                id="schedule-modal-title"
                className="font-semibold text-slate-900 dark:text-zinc-100 text-base"
              >
                Horarios de Atención
              </h3>
              <p className="text-xs text-slate-500 dark:text-zinc-400">
                {restaurantName}
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Cerrar modal de horarios"
            className="p-2 text-slate-400 hover:text-slate-600 dark:text-zinc-400 dark:hover:text-zinc-200 rounded-full hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
          >
            <IconClose size={20} />
          </button>
        </div>

        {/* Schedule List */}
        <div className="p-4 space-y-2 max-h-[60vh] overflow-y-auto">
          {sortedHorarios.length === 0 ? (
            <p className="text-sm text-slate-500 dark:text-zinc-400 text-center py-4">
              No hay horarios registrados.
            </p>
          ) : (
            sortedHorarios.map((item) => {
              const isToday = item.dia_semana === todayDayOfWeek;
              return (
                <div
                  key={item.id}
                  className={`flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-colors ${
                    isToday
                      ? 'bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 font-medium'
                      : 'hover:bg-slate-50 dark:hover:bg-zinc-800/50'
                  }`}
                >
                  <div className="flex items-center gap-2">
                    <span
                      className={`${
                        isToday
                          ? 'text-emerald-900 dark:text-emerald-200 font-semibold'
                          : 'text-slate-800 dark:text-zinc-200'
                      }`}
                    >
                      {item.dia_nombre}
                    </span>
                    {isToday && (
                      <span className="px-1.5 py-0.5 text-[10px] uppercase font-bold tracking-wider rounded bg-emerald-600 text-white dark:bg-emerald-500 dark:text-zinc-950">
                        Hoy
                      </span>
                    )}
                  </div>

                  <div>
                    {item.cerrado || (!item.abre && !item.cierra) ? (
                      <span className="text-rose-600 dark:text-rose-400 text-xs font-medium">
                        Cerrado
                      </span>
                    ) : (
                      <span
                        className={`${
                          isToday
                            ? 'text-emerald-900 dark:text-emerald-200 font-medium'
                            : 'text-slate-600 dark:text-zinc-400'
                        }`}
                      >
                        {item.abre_formateado || item.abre} -{' '}
                        {item.cierra_formateado || item.cierra}
                      </span>
                    )}
                  </div>
                </div>
              );
            })
          )}
        </div>

        {/* Footer */}
        <div className="p-3 bg-slate-50 dark:bg-zinc-900/50 border-t border-slate-100 dark:border-zinc-800 text-center">
          <button
            type="button"
            onClick={onClose}
            className="w-full py-2.5 px-4 bg-slate-900 hover:bg-slate-800 text-white dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200 font-medium text-sm rounded-xl transition-colors min-h-[44px]"
          >
            Entendido
          </button>
        </div>
      </div>
    </div>
  );
}
