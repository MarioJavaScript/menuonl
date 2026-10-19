'use client';

import React, { useState } from 'react';
import { DishItem } from '@/types/menu';
import { DietaryTagBadge, IconPlus, IconUtensils } from './Icons';
import { useCart } from '@/context/CartContext';
import { VariantModal } from './VariantModal';

interface DishCardProps {
  dish: DishItem;
  currency?: string;
}

export function DishCard({ dish, currency = 'MXN' }: DishCardProps) {
  const { addItem } = useCart();
  const [imageError, setImageError] = useState(false);
  const [isVariantModalOpen, setIsVariantModalOpen] = useState(false);

  const hasVariants = dish.variantes && dish.variantes.length > 0;
  const isAvailable = dish.disponible;

  const handleAddClick = () => {
    if (!isAvailable) return;

    if (hasVariants) {
      setIsVariantModalOpen(true);
      return;
    }

    // Direct add for dishes without variants
    addItem({
      dishId: dish.id,
      name: dish.nombre,
      price: dish.precio_vigente,
      formattedPrice: dish.precio_vigente_formateado,
      quantity: 1,
      imageUrl: dish.imagen_url,
    });
  };

  return (
    <>
      <article
        aria-label={dish.nombre}
        className={`group relative flex flex-col justify-between rounded-2xl bg-white dark:bg-zinc-900 border transition-all duration-200 overflow-hidden shadow-xs hover:shadow-md ${
          !isAvailable
            ? 'opacity-60 border-slate-200 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-900/50'
            : 'border-slate-200/80 dark:border-zinc-800/80 hover:border-slate-300 dark:hover:border-zinc-700'
        }`}
      >
        <div className="flex flex-row p-3.5 sm:p-4 gap-3.5 sm:gap-4 items-start">
          {/* Text and Pricing Information */}
          <div className="flex-1 min-w-0">
            {/* Dietary Tags & Highlights */}
            {dish.etiquetas && dish.etiquetas.length > 0 && (
              <div className="flex flex-wrap items-center gap-1.5 mb-2">
                {dish.etiquetas.map((tag) => (
                  <DietaryTagBadge
                    key={tag.id}
                    codigo={tag.codigo}
                    nombre={tag.nombre}
                    icono={tag.icono}
                  />
                ))}
              </div>
            )}

            {/* Dish Name */}
            <h4 className="text-base sm:text-lg font-bold text-slate-900 dark:text-zinc-100 tracking-tight leading-snug line-clamp-2">
              {dish.nombre}
            </h4>

            {/* Dish Description */}
            {dish.descripcion && (
              <p className="mt-1 text-xs sm:text-sm text-slate-600 dark:text-zinc-400 line-clamp-2 leading-relaxed">
                {dish.descripcion}
              </p>
            )}

            {/* Price Display */}
            <div className="mt-2.5 flex flex-wrap items-baseline gap-2">
              {dish.tiene_oferta && dish.precio_oferta_formateado ? (
                <>
                  <span className="text-base sm:text-lg font-black text-rose-600 dark:text-rose-400">
                    {dish.precio_oferta_formateado}
                  </span>
                  <span className="text-xs sm:text-sm text-slate-400 dark:text-zinc-500 line-through">
                    {dish.precio_formateado}
                  </span>
                  <span className="px-1.5 py-0.5 rounded-md text-[10px] font-bold uppercase bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                    Oferta
                  </span>
                </>
              ) : (
                <span className="text-base sm:text-lg font-bold text-slate-900 dark:text-zinc-100">
                  {dish.precio_formateado}
                </span>
              )}

              {hasVariants && (
                <span className="text-[11px] text-slate-500 dark:text-zinc-400">
                  (desde)
                </span>
              )}
            </div>
          </div>

          {/* Dish Image */}
          <div className="relative shrink-0 w-24 h-24 sm:w-28 sm:h-28 rounded-xl overflow-hidden bg-slate-100 dark:bg-zinc-800 border border-slate-200/60 dark:border-zinc-700/60">
            {dish.imagen_url && !imageError ? (
              /* eslint-disable-next-line @next/next/no-img-element */
              <img
                src={dish.imagen_url}
                alt={dish.nombre}
                loading="lazy"
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                onError={() => setImageError(true)}
              />
            ) : (
              <div className="w-full h-full flex flex-col items-center justify-center text-slate-400 dark:text-zinc-600 gap-1 bg-slate-50 dark:bg-zinc-800/60">
                <IconUtensils size={24} />
                <span className="text-[9px] font-medium uppercase tracking-wider">Menú</span>
              </div>
            )}

            {!isAvailable && (
              <div className="absolute inset-0 bg-black/60 flex items-center justify-center">
                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-600 text-white shadow-xs">
                  Agotado
                </span>
              </div>
            )}
          </div>
        </div>

        {/* Action Button Row */}
        <div className="px-3.5 sm:px-4 pb-3.5 sm:pb-4 pt-1 flex items-center justify-between border-t border-slate-100 dark:border-zinc-800/60">
          <div className="text-xs text-slate-500 dark:text-zinc-400 font-medium">
            {hasVariants ? `${dish.variantes.length} opciones` : ''}
          </div>

          <button
            type="button"
            onClick={handleAddClick}
            disabled={!isAvailable}
            aria-label={
              !isAvailable
                ? `${dish.nombre} - Agotado`
                : hasVariants
                ? `Elegir opciones para ${dish.nombre}`
                : `Agregar ${dish.nombre} al pedido`
            }
            className={`inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all min-h-[44px] min-w-[44px] ${
              !isAvailable
                ? 'bg-slate-100 text-slate-400 dark:bg-zinc-800 dark:text-zinc-600 cursor-not-allowed'
                : 'bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white shadow-xs hover:shadow-sm'
            }`}
          >
            {hasVariants ? (
              <span>Elegir opciones</span>
            ) : (
              <>
                <IconPlus size={16} />
                <span>Agregar</span>
              </>
            )}
          </button>
        </div>
      </article>

      {/* Variant Modal if applicable */}
      {hasVariants && (
        <VariantModal
          dish={dish}
          isOpen={isVariantModalOpen}
          onClose={() => setIsVariantModalOpen(false)}
          currency={currency}
        />
      )}
    </>
  );
}
