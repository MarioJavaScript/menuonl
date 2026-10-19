import test from 'node:test';
import assert from 'node:assert/strict';
import { formatPrice } from '../lib/currency';

interface MockCartItem {
  id: string;
  dishId: number;
  variantId?: number;
  name: string;
  variantName?: string | null;
  price: number;
  formattedPrice: string;
  quantity: number;
  notes?: string;
}

// Pure cart reducer simulation to verify business logic and math
function addCartItem(
  items: MockCartItem[],
  dish: {
    dishId: number;
    variantId?: number;
    name: string;
    variantName?: string | null;
    price: number;
    formattedPrice: string;
    quantity?: number;
    notes?: string;
  }
): MockCartItem[] {
  const qty = dish.quantity ?? 1;
  const cleanNotes = (dish.notes || '').trim();
  const variantKey = dish.variantId ? `v${dish.variantId}` : 'base';
  const itemKey = `${dish.dishId}_${variantKey}_${cleanNotes}`;

  const existingIndex = items.findIndex((i) => i.id === itemKey);
  if (existingIndex > -1) {
    const updated = [...items];
    updated[existingIndex] = {
      ...updated[existingIndex],
      quantity: updated[existingIndex].quantity + qty,
    };
    return updated;
  }

  return [
    ...items,
    {
      id: itemKey,
      dishId: dish.dishId,
      variantId: dish.variantId,
      name: dish.name,
      variantName: dish.variantName,
      price: dish.price,
      formattedPrice: dish.formattedPrice,
      quantity: qty,
      notes: cleanNotes || undefined,
    },
  ];
}

function updateCartQuantity(items: MockCartItem[], id: string, delta: number): MockCartItem[] {
  return items
    .map((item) => {
      if (item.id === id) {
        const newQty = item.quantity + delta;
        return newQty > 0 ? { ...item, quantity: newQty } : null;
      }
      return item;
    })
    .filter((item): item is MockCartItem => item !== null);
}

function calculateCartTotals(items: MockCartItem[], currency = 'MXN') {
  const totalCount = items.reduce((sum, item) => sum + item.quantity, 0);
  const totalAmount = items.reduce((sum, item) => sum + item.price * item.quantity, 0);
  const formattedTotal = formatPrice(totalAmount, currency);

  return { totalCount, totalAmount, formattedTotal };
}

test('Cart logic adds new items correctly', () => {
  let cart: MockCartItem[] = [];

  cart = addCartItem(cart, {
    dishId: 1,
    name: 'Taco al Pastor',
    price: 45,
    formattedPrice: '$45.00',
    quantity: 2,
  });

  assert.equal(cart.length, 1);
  assert.equal(cart[0].quantity, 2);
  assert.equal(cart[0].dishId, 1);
});

test('Cart logic merges identical items and accumulates quantity', () => {
  let cart: MockCartItem[] = [];

  cart = addCartItem(cart, {
    dishId: 1,
    variantId: 10,
    name: 'Taco al Pastor',
    variantName: 'Con Queso',
    price: 65,
    formattedPrice: '$65.00',
    quantity: 1,
    notes: 'Sin cebolla',
  });

  cart = addCartItem(cart, {
    dishId: 1,
    variantId: 10,
    name: 'Taco al Pastor',
    variantName: 'Con Queso',
    price: 65,
    formattedPrice: '$65.00',
    quantity: 2,
    notes: 'Sin cebolla',
  });

  assert.equal(cart.length, 1);
  assert.equal(cart[0].quantity, 3);
});

test('Cart logic treats different notes or variants as distinct items', () => {
  let cart: MockCartItem[] = [];

  cart = addCartItem(cart, {
    dishId: 1,
    variantId: 10,
    name: 'Taco al Pastor',
    price: 65,
    formattedPrice: '$65.00',
    quantity: 1,
    notes: 'Sin cebolla',
  });

  cart = addCartItem(cart, {
    dishId: 1,
    variantId: 10,
    name: 'Taco al Pastor',
    price: 65,
    formattedPrice: '$65.00',
    quantity: 1,
    notes: 'Con todo',
  });

  assert.equal(cart.length, 2);
});

test('Cart logic increments and decrements quantity, removing on 0', () => {
  let cart: MockCartItem[] = [];

  cart = addCartItem(cart, {
    dishId: 5,
    name: 'Horchata',
    price: 30,
    formattedPrice: '$30.00',
    quantity: 2,
  });

  const itemId = cart[0].id;
  cart = updateCartQuantity(cart, itemId, 1);
  assert.equal(cart[0].quantity, 3);

  cart = updateCartQuantity(cart, itemId, -2);
  assert.equal(cart[0].quantity, 1);

  cart = updateCartQuantity(cart, itemId, -1);
  assert.equal(cart.length, 0);
});

test('Cart totals calculate correct item counts and formatted monetary sum', () => {
  let cart: MockCartItem[] = [];

  cart = addCartItem(cart, {
    dishId: 1,
    name: 'Taco al Pastor',
    price: 45,
    formattedPrice: '$45.00',
    quantity: 2,
  });

  cart = addCartItem(cart, {
    dishId: 2,
    name: 'Guacamole',
    price: 60,
    formattedPrice: '$60.00',
    quantity: 1,
  });

  const { totalCount, totalAmount, formattedTotal } = calculateCartTotals(cart, 'MXN');
  assert.equal(totalCount, 3);
  assert.equal(totalAmount, 150);
  assert.match(formattedTotal, /\$150\.00/);
});
