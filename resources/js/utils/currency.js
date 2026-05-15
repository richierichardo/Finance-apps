import { formatCurrency } from '@/utils/format';

export function cleanCurrencyValue(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return String(value).replace(/\D/g, '');
}

/** Normalize backend numeric/string values for currency input (integer Rupiah). */
export function normalizeCurrencyRaw(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    if (typeof value === 'number') {
        return String(Math.round(value));
    }

    const str = String(value);

    if (str.includes('.')) {
        return str.split('.')[0].replace(/\D/g, '');
    }

    return cleanCurrencyValue(str);
}

export function formatCurrencyInputDisplay(raw) {
    const cleaned = cleanCurrencyValue(raw);

    if (!cleaned) {
        return '';
    }

    return formatCurrency(Number(cleaned));
}

export function parseCurrencyToNumber(raw) {
    const cleaned = cleanCurrencyValue(raw);

    if (!cleaned) {
        return 0;
    }

    return Number(cleaned);
}
