'use client';

import React from 'react';
import { useCart } from '@/context/CartContext';
import { IconShoppingBag, IconChevronRight } from './Icons';

export function CartFloatingBar() {
  const { totalCount, formattedTotal, openDrawer } = useCart();

  if (totalCount === 0) return null;

  return (
    <div className="fixed bottom-4 inset-x-0 z-40 px-4 pointer-events-none">
      <div className="max-w-md mx-auto pointer-events-auto">
        <button
          type="button"
          onClick={openDrawer}
          aria-label={`Ver pedido actual: ${totalCount} productos por ${formattedTotal}`}
          className="w-full flex items-center justify-between p-3.5 sm:p-4 bg-emerald-600 hover:bg-emerald-500 active:scale-[0.98] text-white rounded-2xl shadow-xl shadow-emerald-900/20 backdrop-blur-md transition-all min-h-[52px]"
        >
          {/* Left badge and counter */}
          <div className="flex items-center gap-3">
            <div className="relative p-2 bg-emerald-700/80 rounded-xl">
              <IconShoppingBag size={20} />
              <span className="absolute -top-1.5 -right-1.5 min-w-[20px] h-5 px-1 bg-white text-emerald-800 font-extrabold text-[11px] rounded-full flex items-center justify-center shadow-xs">
                {totalCount}
              </span>
            </div>

            <div className="text-left">
              <span className="block text-xs font-medium text-emerald-100 uppercase tracking-wider">
                Tu Pedido
              </span>
              <span className="text-sm font-bold">
                {totalCount} {totalCount === 1 ? 'producto' : 'productos'}
              </span>
            </div>
          </div>

          {/* Right total and action */}
          <div className="flex items-center gap-2">
            <span className="font-extrabold text-base sm:text-lg tracking-tight">
              {formattedTotal}
            </span>
            <div className="flex items-center text-xs font-semibold bg-emerald-700/70 py-1.5 px-2.5 rounded-lg">
              <span>Ver Pedido</span>
              <IconChevronRight size={14} className="ml-0.5" />
            </div>
          </div>
        </button>
      </div>
    </div>
  );
}
