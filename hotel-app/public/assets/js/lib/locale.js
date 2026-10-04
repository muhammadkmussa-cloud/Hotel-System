// Locale formatting helpers (P04.08). Display only; pricing logic stays on the server.
const decimalSeparatorCache = new Map();

function decimalSeparator(locale) {
  if (!decimalSeparatorCache.has(locale)) {
    const part = new Intl.NumberFormat(locale).formatToParts(1.1).find((p) => p.type === 'decimal');
    decimalSeparatorCache.set(locale, part ? part.value : '.');
  }
  return decimalSeparatorCache.get(locale);
}

export function formatMoney(minorUnits, { currency = 'KES', locale = 'en-KE' } = {}) {
  if (!Number.isSafeInteger(minorUnits) || minorUnits < 0 || Object.is(minorUnits, -0)) {
    throw new TypeError('Amount must be a non-negative integer of minor units.');
  }
  const value = BigInt(minorUnits);
  const major = value / 100n;
  const cents = (value % 100n).toString().padStart(2, '0');
  const parts = new Intl.NumberFormat(locale, {
    style: 'currency',
    currency,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).formatToParts(major);
  const lastInteger = parts.map((part) => part.type).lastIndexOf('integer');
  return parts
    .map((part, index) => (index === lastInteger ? `${part.value}${decimalSeparator(locale)}${cents}` : part.value))
    .join('');
}

export function formatDateTime(isoUtc, { locale = 'en-KE', timeZone = 'Africa/Nairobi' } = {}) {
  if (typeof isoUtc !== 'string') throw new TypeError('Date must be an ISO-8601 string.');
  const date = new Date(isoUtc);
  if (Number.isNaN(date.getTime())) throw new TypeError('Invalid date.');
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short', timeZone }).format(date);
}
