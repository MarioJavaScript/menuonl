/**
 * Currency formatter matching MENUONL's FormatoMoneda conventions:
 * - MXN: $45.00
 * - USD: $45.00 USD
 * - EUR: 45,00 €
 * - COP: $45,000.00 COP
 */
export function formatPrice(amount: number, currency: string = 'MXN'): string {
  const curr = currency.toUpperCase().trim();

  try {
    if (curr === 'EUR') {
      return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'EUR',
      }).format(amount);
    }

    const formatted = new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN',
    }).format(amount);

    if (curr === 'MXN') {
      return formatted;
    }

    return `${formatted} ${curr}`;
  } catch {
    return `$${amount.toFixed(2)}`;
  }
}
