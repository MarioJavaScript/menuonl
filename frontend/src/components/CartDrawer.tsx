'use client';

import React, { useEffect, useState } from 'react';
import { RestaurantData } from '@/types/menu';
import { useCart } from '@/context/CartContext';
import {
  IconClose,
  IconPlus,
  IconMinus,
  IconTrash,
  IconWhatsApp,
  IconTable,
  IconTakeaway,
  IconAlertCircle,
  IconShoppingBag,
} from './Icons';
import { buildWhatsAppOrderMessage, buildWhatsAppUrl } from '@/lib/whatsapp';
import { formatPrice } from '@/lib/currency';

interface CartDrawerProps {
  restaurant: RestaurantData;
}

export function CartDrawer({ restaurant }: CartDrawerProps) {
  const {
    items,
    isDrawerOpen,
    closeDrawer,
    updateQuantity,
    removeItem,
    setItemNotes,
    clearCart,
    serviceType,
    setServiceType,
    tableNumber,
    setTableNumber,
    kitchenNotes,
    setKitchenNotes,
    totalCount,
    formattedTotal,
    currency,
  } = useCart();

  const [activeNoteEditId, setActiveNoteEditId] = useState<string | null>(null);
  const [tableError, setTableError] = useState<string | null>(null);

  useEffect(() => {
    if (isDrawerOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') closeDrawer();
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = '';
    };
  }, [isDrawerOpen, closeDrawer]);

  if (!isDrawerOpen) return null;

  const canOrder = restaurant.puede_pedir;
  const whatsappPhone = restaurant.whatsapp_pedidos;

  const handleSendWhatsAppOrder = () => {
    if (!canOrder || !whatsappPhone) return;

    if (serviceType === 'mesa' && !tableNumber.trim()) {
      setTableError('Por favor ingresa el número o nombre de tu mesa.');
      return;
    }
    setTableError(null);

    const orderItems = items.map((item) => {
      const itemSubtotal = item.price * item.quantity;
      return {
        quantity: item.quantity,
        name: item.name,
        variantName: item.variantName,
        subtotalFormatted: formatPrice(itemSubtotal, currency),
        notes: item.notes,
      };
    });

    const message = buildWhatsAppOrderMessage({
      restaurantName: restaurant.nombre,
      serviceType,
      tableNumber: serviceType === 'mesa' ? tableNumber.trim() : null,
      items: orderItems,
      kitchenNotes: kitchenNotes.trim() || null,
      totalFormatted: formattedTotal,
    });

    const targetUrl = buildWhatsAppUrl(whatsappPhone, message);
    window.open(targetUrl, '_blank', 'noopener,noreferrer');
  };

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="cart-drawer-title"
      className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-xs animate-in fade-in duration-200"
      onClick={closeDrawer}
    >
      <div
        className="w-full sm:max-w-lg bg-white dark:bg-zinc-900 rounded-t-3xl sm:rounded-2xl shadow-2xl border border-slate-200 dark:border-zinc-800 overflow-hidden max-h-[92vh] flex flex-col"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Drawer Header */}
        <div className="flex items-center justify-between p-4 border-b border-slate-100 dark:border-zinc-800">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">
              <IconShoppingBag size={20} />
            </div>
            <div>
              <h3
                id="cart-drawer-title"
                className="font-bold text-slate-900 dark:text-zinc-100 text-base sm:text-lg"
              >
                Tu Pedido
              </h3>
              <p className="text-xs text-slate-500 dark:text-zinc-400">
                {totalCount} {totalCount === 1 ? 'producto' : 'productos'}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            {items.length > 0 && (
              <button
                type="button"
                onClick={clearCart}
                className="text-xs text-slate-500 hover:text-rose-600 dark:text-zinc-400 dark:hover:text-rose-400 px-2 py-1 rounded-md transition-colors"
              >
                Vaciar
              </button>
            )}
            <button
              type="button"
              onClick={closeDrawer}
              aria-label="Cerrar pedido"
              className="p-2 text-slate-400 hover:text-slate-600 dark:text-zinc-400 dark:hover:text-zinc-200 rounded-full hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
            >
              <IconClose size={20} />
            </button>
          </div>
        </div>

        {/* Empty state */}
        {items.length === 0 ? (
          <div className="p-8 text-center flex flex-col items-center justify-center my-auto">
            <div className="w-16 h-16 rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-400 dark:text-zinc-500 flex items-center justify-center mb-4">
              <IconShoppingBag size={32} />
            </div>
            <h4 className="font-semibold text-slate-800 dark:text-zinc-200 text-base">
              Tu carrito está vacío
            </h4>
            <p className="text-xs text-slate-500 dark:text-zinc-400 mt-1 max-w-xs">
              Explora el menú y agrega tus platillos favoritos para enviar tu orden por WhatsApp.
            </p>
            <button
              type="button"
              onClick={closeDrawer}
              className="mt-6 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-xs sm:text-sm rounded-xl transition-colors min-h-[44px]"
            >
              Ver menú
            </button>
          </div>
        ) : (
          <>
            {/* Scrollable Items & Options */}
            <div className="p-4 space-y-4 overflow-y-auto flex-1">
              {/* Service Type Selector */}
              <div className="space-y-2">
                <label className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">
                  Tipo de entrega:
                </label>
                <div className="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      setServiceType('mesa');
                      setTableError(null);
                    }}
                    className={`flex items-center justify-center gap-2 p-3 rounded-xl border text-xs sm:text-sm font-semibold transition-all min-h-[48px] ${
                      serviceType === 'mesa'
                        ? 'border-emerald-600 bg-emerald-50/70 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200 dark:border-emerald-700 shadow-xs'
                        : 'border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800'
                    }`}
                  >
                    <IconTable size={16} />
                    <span>En Mesa</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => {
                      setServiceType('llevar');
                      setTableError(null);
                    }}
                    className={`flex items-center justify-center gap-2 p-3 rounded-xl border text-xs sm:text-sm font-semibold transition-all min-h-[48px] ${
                      serviceType === 'llevar'
                        ? 'border-emerald-600 bg-emerald-50/70 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200 dark:border-emerald-700 shadow-xs'
                        : 'border-slate-200 dark:border-zinc-800 text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800'
                    }`}
                  >
                    <IconTakeaway size={16} />
                    <span>Para Llevar</span>
                  </button>
                </div>

                {serviceType === 'mesa' && (
                  <div className="mt-2 space-y-1">
                    <div className="relative">
                      <input
                        type="text"
                        value={tableNumber}
                        onChange={(e) => {
                          setTableNumber(e.target.value);
                          if (e.target.value.trim()) setTableError(null);
                        }}
                        placeholder="Número o identificador de mesa (ej. 4, Terraza 2)"
                        maxLength={20}
                        className="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-800/50 text-slate-900 dark:text-zinc-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                      />
                    </div>
                    {tableError && (
                      <p className="text-xs text-rose-600 dark:text-rose-400 font-medium">
                        {tableError}
                      </p>
                    )}
                  </div>
                )}
              </div>

              {/* Items List */}
              <div className="space-y-3">
                <label className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">
                  Platillos agregados:
                </label>

                <div className="space-y-2.5">
                  {items.map((item) => {
                    const itemSubtotal = item.price * item.quantity;
                    const isEditingNote = activeNoteEditId === item.id;

                    return (
                      <div
                        key={item.id}
                        className="p-3 rounded-xl border border-slate-200 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/40 space-y-2"
                      >
                        <div className="flex items-start justify-between gap-3">
                          <div className="flex-1 min-w-0">
                            <h5 className="font-bold text-sm text-slate-900 dark:text-zinc-100 leading-snug">
                              {item.name}
                            </h5>
                            {item.variantName && (
                              <span className="inline-block text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                Opción: {item.variantName}
                              </span>
                            )}
                            <div className="text-xs font-semibold text-slate-600 dark:text-zinc-400 mt-0.5">
                              {formatPrice(itemSubtotal, currency)}
                            </div>
                          </div>

                          {/* Stepper + Trash */}
                          <div className="flex items-center gap-1.5 shrink-0">
                            <button
                              type="button"
                              onClick={() => updateQuantity(item.id, -1)}
                              aria-label="Disminuir cantidad"
                              className="w-8 h-8 rounded-lg border border-slate-300 dark:border-zinc-700 flex items-center justify-center text-slate-700 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors"
                            >
                              <IconMinus size={14} />
                            </button>
                            <span className="font-bold text-sm min-w-[20px] text-center text-slate-900 dark:text-zinc-100">
                              {item.quantity}
                            </span>
                            <button
                              type="button"
                              onClick={() => updateQuantity(item.id, 1)}
                              aria-label="Aumentar cantidad"
                              className="w-8 h-8 rounded-lg border border-slate-300 dark:border-zinc-700 flex items-center justify-center text-slate-700 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors"
                            >
                              <IconPlus size={14} />
                            </button>
                            <button
                              type="button"
                              onClick={() => removeItem(item.id)}
                              aria-label="Eliminar producto"
                              className="w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 flex items-center justify-center transition-colors ml-1"
                            >
                              <IconTrash size={15} />
                            </button>
                          </div>
                        </div>

                        {/* Note snippet or edit field */}
                        <div className="pt-1 text-xs">
                          {isEditingNote ? (
                            <div className="flex items-center gap-2">
                              <input
                                type="text"
                                defaultValue={item.notes || ''}
                                placeholder="Nota especial (ej. sin cebolla)"
                                autoFocus
                                onKeyDown={(e) => {
                                  if (e.key === 'Enter') {
                                    setItemNotes(item.id, (e.target as HTMLInputElement).value);
                                    setActiveNoteEditId(null);
                                  }
                                }}
                                onBlur={(e) => {
                                  setItemNotes(item.id, e.target.value);
                                  setActiveNoteEditId(null);
                                }}
                                className="flex-1 px-2.5 py-1 text-xs rounded-lg border border-slate-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-slate-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                              />
                              <button
                                type="button"
                                onClick={() => setActiveNoteEditId(null)}
                                className="text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                              >
                                Listo
                              </button>
                            </div>
                          ) : (
                            <div className="flex items-center justify-between">
                              {item.notes ? (
                                <span className="text-slate-600 dark:text-zinc-400 italic">
                                  Nota: &quot;{item.notes}&quot;
                                </span>
                              ) : (
                                <span className="text-slate-400 dark:text-zinc-500">
                                  Sin notas
                                </span>
                              )}
                              <button
                                type="button"
                                onClick={() => setActiveNoteEditId(item.id)}
                                className="text-[11px] font-medium text-emerald-600 dark:text-emerald-400 hover:underline"
                              >
                                {item.notes ? 'Editar nota' : '+ Agregar nota'}
                              </button>
                            </div>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>

              {/* General Kitchen Notes */}
              <div className="space-y-1.5">
                <label
                  htmlFor="kitchen-notes-area"
                  className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400"
                >
                  Nota para la cocina (opcional):
                </label>
                <textarea
                  id="kitchen-notes-area"
                  rows={2}
                  value={kitchenNotes}
                  onChange={(e) => setKitchenNotes(e.target.value)}
                  placeholder="Instrucciones adicionales para todo el pedido (alergias, tiempos, cubiertos, etc.)..."
                  maxLength={300}
                  className="w-full px-3 py-2 text-xs sm:text-sm rounded-xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-800/50 text-slate-900 dark:text-zinc-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"
                />
              </div>

              {/* Order Ineligibility Warning */}
              {!canOrder && (
                <div className="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 flex items-start gap-2 text-amber-800 dark:text-amber-200 text-xs">
                  <IconAlertCircle size={16} className="shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
                  <div>
                    <span className="font-bold">Pedidos no disponibles: </span>
                    <span>
                      {!restaurant.abierto
                        ? 'El restaurante se encuentra cerrado en este momento.'
                        : !restaurant.pedidos_activos
                        ? 'Los pedidos por WhatsApp están temporalmente desactivados.'
                        : 'El restaurante no tiene configurado un número de WhatsApp para pedidos.'}
                    </span>
                  </div>
                </div>
              )}
            </div>

            {/* Drawer Footer with WhatsApp Action */}
            <div className="p-4 bg-slate-50 dark:bg-zinc-900/90 border-t border-slate-100 dark:border-zinc-800 space-y-3">
              {/* Subtotal & Total */}
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-slate-600 dark:text-zinc-400">
                  Total a pagar:
                </span>
                <span className="text-xl sm:text-2xl font-black text-slate-900 dark:text-zinc-100">
                  {formattedTotal}
                </span>
              </div>

              {/* Order via WhatsApp Button */}
              <button
                type="button"
                onClick={handleSendWhatsAppOrder}
                disabled={!canOrder || items.length === 0}
                className="w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-500 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-sm sm:text-base rounded-xl transition-all shadow-lg shadow-emerald-900/20 flex items-center justify-center gap-2 min-h-[48px]"
              >
                <IconWhatsApp size={20} />
                <span>Pedir por WhatsApp</span>
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
