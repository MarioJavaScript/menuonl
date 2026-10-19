import test from 'node:test';
import assert from 'node:assert/strict';
import {
  buildWhatsAppOrderMessage,
  cleanPhoneNumber,
  buildWhatsAppUrl,
} from './whatsapp';

test('cleanPhoneNumber strips formatting and symbols', () => {
  assert.equal(cleanPhoneNumber('+52 (55) 1234-5678'), '525512345678');
  assert.equal(cleanPhoneNumber('+1-800-555-0199'), '18005550199');
  assert.equal(cleanPhoneNumber('5512345678'), '5512345678');
});

test('buildWhatsAppOrderMessage formats table order with items and notes', () => {
  const message = buildWhatsAppOrderMessage({
    restaurantName: 'Taquería La Auténtica',
    serviceType: 'mesa',
    tableNumber: 'Mesa 4',
    items: [
      {
        quantity: 2,
        name: 'Taco al Pastor',
        variantName: 'Con Queso Fundido',
        subtotalFormatted: '$130.00',
        notes: 'Sin cebolla por favor',
      },
      {
        quantity: 1,
        name: 'Guacamole Tradicional',
        variantName: null,
        subtotalFormatted: '$60.00',
        notes: null,
      },
    ],
    kitchenNotes: 'Salsa roja aparte',
    totalFormatted: '$190.00',
  });

  const expected = [
    'Hola, quiero pedir en *Taquería La Auténtica*:',
    '',
    '*Mesa:* Mesa 4',
    '',
    '*Pedido:*',
    '• 2x Taco al Pastor (Con Queso Fundido) - $130.00',
    '  _Nota: Sin cebolla por favor_',
    '• 1x Guacamole Tradicional - $60.00',
    '',
    '*Nota para la cocina:* Salsa roja aparte',
    '',
    '*Total:* $190.00',
  ].join('\n');

  assert.equal(message, expected);
});

test('buildWhatsAppOrderMessage formats takeaway order without kitchen notes', () => {
  const message = buildWhatsAppOrderMessage({
    restaurantName: 'Burger Palace',
    serviceType: 'llevar',
    items: [
      {
        quantity: 1,
        name: 'Cheeseburger',
        subtotalFormatted: '$12.00',
      },
    ],
    totalFormatted: '$12.00',
  });

  const expected = [
    'Hola, quiero pedir en *Burger Palace*:',
    '',
    '*Mesa:* Para llevar',
    '',
    '*Pedido:*',
    '• 1x Cheeseburger - $12.00',
    '',
    '*Total:* $12.00',
  ].join('\n');

  assert.equal(message, expected);
});

test('buildWhatsAppUrl constructs valid wa.me URL', () => {
  const url = buildWhatsAppUrl('+52 55 1234 5678', 'Hola');
  assert.equal(url, 'https://wa.me/525512345678?text=Hola');
});
