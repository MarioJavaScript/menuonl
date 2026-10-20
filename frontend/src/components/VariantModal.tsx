'use client';

import React, { useState, useEffect } from 'react';
import { DishItem, DishVariantItem } from '@/types/menu';
import { IconClose, IconPlus, IconMinus, IconCheck } from './Icons';
import { useCart } from '@/context/CartContext';
import { formatPrice } from '@/lib/currency';

interface VariantModalProps {
  dish: DishItem;
  isOpen: boolean;
  onClose: () => void;
  currency?: string;
}

export function VariantModal({
  dish,
  isOpen,
  onClose,
  currency = 'MXN',
}: VariantModalProps) {
  const { addItem } = useCart();
  const [selectedVariant, setSelectedVariant] = useState<DishVariantItem | null>(
    dish.variantes[0] || null
  );
  const [quantity, setQuantity] = useState(1);
  const [notes, setNotes] = useState('');

  // Reset state when modal opens for a dish
  useEffect(() => {
    if (isOpen) {
      setSelectedVariant(dish.variantes[0] || null);
      setQuantity(1);
      setNotes('');
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = '';
    };
  }, [isOpen, dish, onClose]);

  if (!isOpen || !selectedVariant) return null;

  const currentUnitPrice = selectedVariant.precio;
  const currentFormattedUnitPrice = selectedVariant.precio_formateado;
  const subtotal = currentUnitPrice * quantity;
  const formattedSubtotal = formatPrice(subtotal, currency);

  const handleAddToCart = () => {
    addItem({
      dishId: dish.id,
      variantId: selectedVariant.id,
      name: dish.nombre,
      variantName: selectedVariant.nombre,
      price: currentUnitPrice,
      formattedPrice: currentFormattedUnitPrice,
      quantity,
      notes,
      imageUrl: dish.imagen_url,
    });
    onClose();
  };

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="variant-modal-title"
      className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-xs animate-in fade-in duration-200"
      onClick={onClose}
    >
      <div
        className="w-full sm:max-w-md bg-white dark:bg-zinc-900 rounded-t-3xl sm:rounded-2xl shadow-2xl border border-slate-200 dark:border-zinc-800 overflow-hidden max-h-[90vh] flex flex-col"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-slate-100 dark:border-zinc-800">
          <div>
            <h3
              id="variant-modal-title"
              className="font-bold text-slate-900 dark:text-zinc-100 text-lg leading-tight"
            >
              {dish.nombre}
            </h3>
            <p className="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
              Selecciona una opción
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Cerrar selección de opciones"
            className="p-2 text-slate-400 hover:text-slate-600 dark:text-zinc-400 dark:hover:text-zinc-200 rounded-full hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
          >
            <IconClose size={20} />
          </button>
        </div>

        {/* Content Body */}
        <div className="p-4 space-y-4 overflow-y-auto">
          {dish.descripcion && (
            <p className="text-xs text-slate-600 dark:text-zinc-400 leading-relaxed">
              {dish.descripcion}
            </p>
          )}

          {/* Variants List */}
          <div className="space-y-2">
            <label className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">
              Opciones disponibles:
            </label>
            <div className="space-y-2">
              {dish.variantes.map((v) => {
                const isSelected = selectedVariant.id === v.id;
                const isAvailable = v.disponible;

                return (
                  <button
                    key={v.id}
                    type="button"
                    disabled={!isAvailable}
                    onClick={() => setSelectedVariant(v)}
                    className={`w-full flex items-center justify-between p-3 rounded-xl border text-left transition-all min-h-[50px] ${
                      !isAvailable
                        ? 'opacity-40 bg-slate-50 dark:bg-zinc-800/40 border-slate-200 dark:border-zinc-800 cursor-not-allowed'
                        : isSelected
                        ? 'border-emerald-600 bg-emerald-50/70 dark:bg-emerald-950/30 text-emerald-900 dark:text-emerald-200 shadow-xs'
                        : 'border-slate-200 dark:border-zinc-800 hover:border-slate-300 dark:hover:border-zinc-700 bg-white dark:bg-zinc-800/60'
                    }`}
                  >
                    <div className="flex items-center gap-2.5">
                      <div
                        className={`w-5 h-5 rounded-full border flex items-center justify-center shrink-0 ${
                          isSelected
                            ? 'border-emerald-600 bg-emerald-600 text-white'
                            : 'border-slate-300 dark:border-zinc-600'
                        }`}
                      >
                        {isSelected && <IconCheck size={12} />}
                      </div>
                      <span className="font-medium text-sm text-slate-900 dark:text-zinc-100">
                        {v.nombre}
                      </span>
                    </div>

                    <div className="flex items-center gap-2">
                      <span className="font-bold text-sm text-slate-900 dark:text-zinc-100">
                        {v.precio_formateado}
                      </span>
                      {!isAvailable && (
                        <span className="text-[10px] text-rose-600 font-semibold uppercase">
                          Agotado
                        </span>
                      )}
                    </div>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Dish Notes */}
          <div className="space-y-1.5">
            <label
              htmlFor="dish-variant-notes"
              className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400"
            >
              Instrucciones especiales (opcional):
            </label>
            <input
              id="dish-variant-notes"
              type="text"
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Ej: Sin cebolla, término medio, etc."
              maxLength={150}
              className="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-800/50 text-slate-900 dark:text-zinc-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </div>

          {/* Quantity Stepper */}
          <div className="flex items-center justify-between pt-2">
            <span className="text-sm font-medium text-slate-700 dark:text-zinc-300">
              Cantidad:
            </span>
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => setQuantity((q) => Math.max(1, q - 1))}
                disabled={quantity <= 1}
                aria-label="Disminuir cantidad"
                className="w-10 h-10 rounded-full border border-slate-300 dark:border-zinc-700 flex items-center justify-center text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800 disabled:opacity-40 transition-colors"
              >
                <IconMinus size={16} />
              </button>
              <span className="font-bold text-base min-w-[24px] text-center text-slate-900 dark:text-zinc-100">
                {quantity}
              </span>
              <button
                type="button"
                onClick={() => setQuantity((q) => q + 1)}
                aria-label="Aumentar cantidad"
                className="w-10 h-10 rounded-full border border-slate-300 dark:border-zinc-700 flex items-center justify-center text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors"
              >
                <IconPlus size={16} />
              </button>
            </div>
          </div>
        </div>

        {/* Footer Action */}
        <div className="p-4 bg-slate-50 dark:bg-zinc-900/80 border-t border-slate-100 dark:border-zinc-800">
          <button
            type="button"
            onClick={handleAddToCart}
            disabled={!selectedVariant.disponible}
            className="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm rounded-xl transition-all shadow-md flex items-center justify-between min-h-[48px] disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span>Agregar al pedido</span>
            <span>{formattedSubtotal}</span>
          </button>
        </div>
      </div>
    </div>
  );
}
