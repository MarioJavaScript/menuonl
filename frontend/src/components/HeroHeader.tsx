'use client';

import React, { useState } from 'react';
import { RestaurantData } from '@/types/menu';
import { IconClock, IconPhone, IconWhatsApp, IconTable } from './Icons';
import { ScheduleModal } from './ScheduleModal';

interface HeroHeaderProps {
  restaurant: RestaurantData;
  activeTable?: string | null;
}

export function HeroHeader({ restaurant, activeTable }: HeroHeaderProps) {
  const [isScheduleOpen, setIsScheduleOpen] = useState(false);
  const [imageError, setImageError] = useState(false);
  const [coverError, setCoverError] = useState(false);

  const tableIdentifier = activeTable || restaurant.mesa;

  // Find today's schedule
  const todayDayOfWeek = new Date().getDay();
  const todaySchedule = restaurant.horarios.find(
    (h) => h.dia_semana === todayDayOfWeek
  );

  let scheduleSummary = 'Hoy: Cerrado';
  if (todaySchedule && !todaySchedule.cerrado && (todaySchedule.abre || todaySchedule.abre_formateado)) {
    scheduleSummary = `Hoy: ${todaySchedule.abre_formateado || todaySchedule.abre} - ${
      todaySchedule.cierra_formateado || todaySchedule.cierra
    }`;
  }

  // Accent color styles if provided
  const accentColor = restaurant.color_acento || '#0ea5e9';

  return (
    <header className="relative w-full bg-white dark:bg-zinc-900 border-b border-slate-200 dark:border-zinc-800 transition-colors">
      {/* Cover Banner */}
      <div className="relative w-full h-44 sm:h-56 md:h-64 overflow-hidden bg-gradient-to-r from-slate-800 via-slate-700 to-slate-900">
        {restaurant.portada_url && !coverError ? (
          /* eslint-disable-next-line @next/next/no-img-element */
          <img
            src={restaurant.portada_url}
            alt={`Portada de ${restaurant.nombre}`}
            className="w-full h-full object-cover"
            onError={() => setCoverError(true)}
          />
        ) : (
          <div
            className="w-full h-full opacity-60"
            style={{
              background: `linear-gradient(135deg, ${accentColor}33 0%, #1e293b 100%)`,
            }}
          />
        )}
        {/* Gradient Overlay */}
        <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-black/10" />

        {/* Floating Top Badges (Table & Contacts) */}
        <div className="absolute top-3 right-3 sm:top-4 sm:right-4 flex flex-wrap items-center gap-2 z-10">
          {tableIdentifier && (
            <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white/95 text-slate-900 dark:bg-zinc-900/90 dark:text-zinc-100 shadow-md backdrop-blur-md border border-white/20">
              <IconTable size={14} className="text-emerald-600 dark:text-emerald-400" />
              <span>Mesa {tableIdentifier}</span>
            </div>
          )}

          {restaurant.whatsapp_pedidos && (
            <a
              href={`https://wa.me/${restaurant.whatsapp_pedidos.replace(/\D/g, '')}`}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-600 hover:bg-emerald-500 text-white shadow-md backdrop-blur-md transition-colors min-h-[32px]"
              title="Contactar por WhatsApp"
            >
              <IconWhatsApp size={14} />
              <span className="hidden sm:inline">WhatsApp</span>
            </a>
          )}
        </div>
      </div>

      {/* Identity Container */}
      <div className="max-w-5xl mx-auto px-4 sm:px-6 pb-6">
        <div className="relative -mt-14 sm:-mt-16 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
          {/* Logo / Monogram & Name */}
          <div className="flex items-end gap-3.5 sm:gap-4">
            <div className="relative shrink-0 w-24 h-24 sm:w-28 sm:h-28 rounded-2xl bg-white dark:bg-zinc-800 shadow-xl border-4 border-white dark:border-zinc-900 overflow-hidden flex items-center justify-center">
              {restaurant.logo_url && !imageError ? (
                /* eslint-disable-next-line @next/next/no-img-element */
                <img
                  src={restaurant.logo_url}
                  alt={restaurant.nombre}
                  className="w-full h-full object-cover"
                  onError={() => setImageError(true)}
                />
              ) : (
                <div
                  className="w-full h-full flex items-center justify-center font-bold text-3xl sm:text-4xl text-white select-none shadow-inner"
                  style={{
                    backgroundColor: accentColor,
                  }}
                >
                  {restaurant.monograma || restaurant.nombre.charAt(0).toUpperCase()}
                </div>
              )}
            </div>

            {/* Name and Slogan */}
            <div className="pb-1">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-xl sm:text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-zinc-50 tracking-tight">
                  {restaurant.nombre}
                </h1>

                {/* Status Badge */}
                {restaurant.abierto ? (
                  <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                    <span className="relative flex h-2 w-2">
                      <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                      <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" />
                    </span>
                    Abierto ahora
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                    <span className="h-2 w-2 rounded-full bg-rose-500" />
                    Cerrado ahora
                  </span>
                )}
              </div>

              {restaurant.eslogan && (
                <p className="text-sm font-medium text-slate-600 dark:text-zinc-400 mt-0.5">
                  {restaurant.eslogan}
                </p>
              )}
            </div>
          </div>

          {/* Quick Schedule Trigger Button */}
          <button
            type="button"
            onClick={() => setIsScheduleOpen(true)}
            className="self-start sm:self-auto inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-zinc-300 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 rounded-xl transition-colors min-h-[36px]"
          >
            <IconClock size={15} className="text-slate-500 dark:text-zinc-400" />
            <span>{scheduleSummary}</span>
            <span className="text-[11px] underline text-slate-500 dark:text-zinc-400 ml-1">
              Ver semana
            </span>
          </button>
        </div>

        {/* Description */}
        {restaurant.descripcion && (
          <p className="mt-3 text-sm text-slate-600 dark:text-zinc-400 line-clamp-3 leading-relaxed max-w-3xl">
            {restaurant.descripcion}
          </p>
        )}

        {/* Contact info snippets if available */}
        {restaurant.contactos.length > 0 && (
          <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-zinc-400">
            {restaurant.contactos
              .filter((c) => c.tipo === 'telefono' || c.tipo === 'whatsapp')
              .slice(0, 2)
              .map((c) => (
                <a
                  key={c.id}
                  href={c.url}
                  className="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-zinc-200 transition-colors"
                >
                  {c.tipo === 'whatsapp' ? (
                    <IconWhatsApp size={14} className="text-emerald-600" />
                  ) : (
                    <IconPhone size={14} />
                  )}
                  <span>{c.valor}</span>
                </a>
              ))}
          </div>
        )}
      </div>

      {/* Schedule Modal */}
      <ScheduleModal
        isOpen={isScheduleOpen}
        onClose={() => setIsScheduleOpen(false)}
        horarios={restaurant.horarios}
        restaurantName={restaurant.nombre}
      />
    </header>
  );
}
