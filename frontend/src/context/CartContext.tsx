'use client';

import React, {
  createContext,
  useContext,
  useState,
  useEffect,
  useCallback,
  useMemo,
} from 'react';
import { formatPrice } from '@/lib/currency';

export type ServiceType = 'mesa' | 'llevar';

export interface CartItem {
  id: string; // Unique cart item identifier (e.g. `${dishId}_${variantId || '0'}_${noteHash}`)
  dishId: number;
  variantId?: number;
  name: string;
  variantName?: string | null;
  price: number;
  formattedPrice: string;
  quantity: number;
  notes?: string;
  imageUrl?: string | null;
}

export interface CartContextValue {
  slug: string;
  items: CartItem[];
  addItem: (item: {
    dishId: number;
    variantId?: number;
    name: string;
    variantName?: string | null;
    price: number;
    formattedPrice: string;
    quantity?: number;
    notes?: string;
    imageUrl?: string | null;
  }) => void;
  removeItem: (id: string) => void;
  updateQuantity: (id: string, delta: number) => void;
  setItemNotes: (id: string, notes: string) => void;
  clearCart: () => void;
  serviceType: ServiceType;
  setServiceType: (type: ServiceType) => void;
  tableNumber: string;
  setTableNumber: (table: string) => void;
  kitchenNotes: string;
  setKitchenNotes: (notes: string) => void;
  totalCount: number;
  totalAmount: number;
  formattedTotal: string;
  isDrawerOpen: boolean;
  setIsDrawerOpen: (open: boolean) => void;
  openDrawer: () => void;
  closeDrawer: () => void;
  currency: string;
}

const CartContext = createContext<CartContextValue | null>(null);

interface CartProviderProps {
  slug: string;
  currency?: string;
  initialTable?: string | null;
  children: React.ReactNode;
}

interface StoredCartData {
  items: CartItem[];
  serviceType: ServiceType;
  tableNumber: string;
  kitchenNotes: string;
}

export function CartProvider({
  slug,
  currency = 'MXN',
  initialTable,
  children,
}: CartProviderProps) {
  const [items, setItems] = useState<CartItem[]>([]);
  const [serviceType, setServiceType] = useState<ServiceType>(
    initialTable ? 'mesa' : 'mesa'
  );
  const [tableNumber, setTableNumber] = useState<string>(initialTable || '');
  const [kitchenNotes, setKitchenNotes] = useState<string>('');
  const [isDrawerOpen, setIsDrawerOpen] = useState<boolean>(false);
  const [isHydrated, setIsHydrated] = useState<boolean>(false);

  const storageKey = `menuonl_cart_${slug}`;

  // Hydrate from localStorage once mounted
  useEffect(() => {
    try {
      const raw = localStorage.getItem(storageKey);
      if (raw) {
        const parsed: StoredCartData = JSON.parse(raw);
        if (Array.isArray(parsed.items)) {
          setItems(parsed.items);
        }
        if (parsed.serviceType === 'mesa' || parsed.serviceType === 'llevar') {
          setServiceType(parsed.serviceType);
        }
        if (typeof parsed.tableNumber === 'string' && parsed.tableNumber) {
          setTableNumber(parsed.tableNumber);
        } else if (initialTable) {
          setTableNumber(initialTable);
        }
        if (typeof parsed.kitchenNotes === 'string') {
          setKitchenNotes(parsed.kitchenNotes);
        }
      } else if (initialTable) {
        setTableNumber(initialTable);
      }
    } catch {
      // Ignore corrupted localStorage data
    } finally {
      setIsHydrated(true);
    }
  }, [storageKey, initialTable]);

  // Persist to localStorage whenever state changes after hydration
  useEffect(() => {
    if (!isHydrated) return;
    try {
      const dataToStore: StoredCartData = {
        items,
        serviceType,
        tableNumber,
        kitchenNotes,
      };
      localStorage.setItem(storageKey, JSON.stringify(dataToStore));
    } catch {
      // Local storage quota or security error ignored
    }
  }, [items, serviceType, tableNumber, kitchenNotes, storageKey, isHydrated]);

  const addItem = useCallback(
    (params: {
      dishId: number;
      variantId?: number;
      name: string;
      variantName?: string | null;
      price: number;
      formattedPrice: string;
      quantity?: number;
      notes?: string;
      imageUrl?: string | null;
    }) => {
      const quantityToAdd = params.quantity ?? 1;
      const cleanNotes = (params.notes || '').trim();
      const variantKey = params.variantId ? `v${params.variantId}` : 'base';
      const itemKey = `${params.dishId}_${variantKey}_${cleanNotes}`;

      setItems((prev) => {
        const existingIndex = prev.findIndex((item) => item.id === itemKey);
        if (existingIndex > -1) {
          const updated = [...prev];
          updated[existingIndex] = {
            ...updated[existingIndex],
            quantity: updated[existingIndex].quantity + quantityToAdd,
          };
          return updated;
        }

        const newItem: CartItem = {
          id: itemKey,
          dishId: params.dishId,
          variantId: params.variantId,
          name: params.name,
          variantName: params.variantName,
          price: params.price,
          formattedPrice: params.formattedPrice,
          quantity: quantityToAdd,
          notes: cleanNotes || undefined,
          imageUrl: params.imageUrl,
        };

        return [...prev, newItem];
      });
    },
    []
  );

  const removeItem = useCallback((id: string) => {
    setItems((prev) => prev.filter((item) => item.id !== id));
  }, []);

  const updateQuantity = useCallback(
    (id: string, delta: number) => {
      setItems((prev) =>
        prev
          .map((item) => {
            if (item.id === id) {
              const newQuantity = item.quantity + delta;
              return newQuantity > 0
                ? { ...item, quantity: newQuantity }
                : null;
            }
            return item;
          })
          .filter((item): item is CartItem => item !== null)
      );
    },
    []
  );

  const setItemNotes = useCallback((id: string, notes: string) => {
    setItems((prev) =>
      prev.map((item) =>
        item.id === id ? { ...item, notes: notes.trim() || undefined } : item
      )
    );
  }, []);

  const clearCart = useCallback(() => {
    setItems([]);
    setKitchenNotes('');
    try {
      localStorage.removeItem(storageKey);
    } catch {
      // Ignored
    }
  }, [storageKey]);

  const totalCount = useMemo(
    () => items.reduce((sum, item) => sum + item.quantity, 0),
    [items]
  );

  const totalAmount = useMemo(
    () => items.reduce((sum, item) => sum + item.price * item.quantity, 0),
    [items]
  );

  const formattedTotal = useMemo(
    () => formatPrice(totalAmount, currency),
    [totalAmount, currency]
  );

  const openDrawer = useCallback(() => setIsDrawerOpen(true), []);
  const closeDrawer = useCallback(() => setIsDrawerOpen(false), []);

  const value: CartContextValue = {
    slug,
    items,
    addItem,
    removeItem,
    updateQuantity,
    setItemNotes,
    clearCart,
    serviceType,
    setServiceType,
    tableNumber,
    setTableNumber,
    kitchenNotes,
    setKitchenNotes,
    totalCount,
    totalAmount,
    formattedTotal,
    isDrawerOpen,
    setIsDrawerOpen,
    openDrawer,
    closeDrawer,
    currency,
  };

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartContextValue {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used within a CartProvider');
  }
  return context;
}
