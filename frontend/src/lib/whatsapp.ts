export interface WhatsAppOrderItem {
  quantity: number;
  name: string;
  variantName?: string | null;
  subtotalFormatted: string;
  notes?: string | null;
}

export interface BuildWhatsAppOrderParams {
  restaurantName: string;
  serviceType: 'mesa' | 'llevar';
  tableNumber?: string | null;
  items: WhatsAppOrderItem[];
  kitchenNotes?: string | null;
  totalFormatted: string;
}

/**
 * Builds a structured, formatted text message for WhatsApp ordering
 * matching the authoritative contract in PROJECT.md and ORIGINAL_REQUEST.md.
 */
export function buildWhatsAppOrderMessage(params: BuildWhatsAppOrderParams): string {
  const {
    restaurantName,
    serviceType,
    tableNumber,
    items,
    kitchenNotes,
    totalFormatted,
  } = params;

  const mesaText = serviceType === 'mesa' && tableNumber?.trim()
    ? tableNumber.trim()
    : 'Para llevar';

  const lines: string[] = [];
  lines.push(`Hola, quiero pedir en *${restaurantName}*:`);
  lines.push('');
  lines.push(`*Mesa:* ${mesaText}`);
  lines.push('');
  lines.push('*Pedido:*');

  for (const item of items) {
    const variantPart = item.variantName ? ` (${item.variantName})` : '';
    lines.push(`• ${item.quantity}x ${item.name}${variantPart} - ${item.subtotalFormatted}`);
    if (item.notes && item.notes.trim()) {
      lines.push(`  _Nota: ${item.notes.trim()}_`);
    }
  }

  lines.push('');

  if (kitchenNotes && kitchenNotes.trim()) {
    lines.push(`*Nota para la cocina:* ${kitchenNotes.trim()}`);
    lines.push('');
  }

  lines.push(`*Total:* ${totalFormatted}`);

  return lines.join('\n');
}

/**
 * Cleans phone number by stripping spaces, symbols, and formatting marks.
 * Returns only digits.
 */
export function cleanPhoneNumber(rawPhone: string): string {
  return rawPhone.replace(/\D/g, '');
}

/**
 * Constructs the wa.me link with encoded text message.
 */
export function buildWhatsAppUrl(rawPhone: string, message: string): string {
  const cleanPhone = cleanPhoneNumber(rawPhone);
  const encoded = encodeURIComponent(message);
  return `https://wa.me/${cleanPhone}?text=${encoded}`;
}
