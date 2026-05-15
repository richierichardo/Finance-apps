/**
 * Date display/parsing utilities for Indonesian dd/mm/yyyy format.
 * Raw backend formats: Y-m-d (dates), ISO 8601 (datetimes).
 */

function digitsOnly(value) {
    return String(value ?? '').replace(/\D/g, '');
}

export function formatDateDisplay(raw) {
    if (!raw) return '';

    const str = String(raw).trim();

    if (/^\d{4}-\d{2}-\d{2}/.test(str)) {
        const [y, m, d] = str.slice(0, 10).split('-');
        return `${d}/${m}/${y}`;
    }

    const digits = digitsOnly(str);
    if (digits.length === 8) {
        return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4, 8)}`;
    }

    return str;
}

export function maskDateInput(value) {
    const digits = digitsOnly(value).slice(0, 8);

    if (digits.length <= 2) return digits;
    if (digits.length <= 4) return `${digits.slice(0, 2)}/${digits.slice(2)}`;

    return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
}

export function isValidDateDisplay(display) {
    const raw = parseDateDisplayToRaw(display);
    if (!raw) return display.trim() === '';

    const [y, m, d] = raw.split('-').map(Number);
    const date = new Date(y, m - 1, d);

    return (
        date.getFullYear() === y &&
        date.getMonth() === m - 1 &&
        date.getDate() === d
    );
}

export function parseDateDisplayToRaw(display) {
    if (!display || !String(display).trim()) return '';

    const digits = digitsOnly(display);
    if (digits.length !== 8) return '';

    const day = parseInt(digits.slice(0, 2), 10);
    const month = parseInt(digits.slice(2, 4), 10);
    const year = parseInt(digits.slice(4, 8), 10);

    if (day < 1 || day > 31 || month < 1 || month > 12 || year < 1000) {
        return '';
    }

    const dd = String(day).padStart(2, '0');
    const mm = String(month).padStart(2, '0');

    return `${year}-${mm}-${dd}`;
}

export function formatDateTimeDisplay(isoOrRaw) {
    if (!isoOrRaw) return '';

    const date = new Date(isoOrRaw);
    if (Number.isNaN(date.getTime())) {
        return formatDateDisplay(isoOrRaw);
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${day}/${month}/${year} ${hours}:${minutes}`;
}

export function maskDateTimeInput(value) {
    const digits = digitsOnly(value).slice(0, 12);

    if (digits.length <= 2) return digits;
    if (digits.length <= 4) return `${digits.slice(0, 2)}/${digits.slice(2)}`;
    if (digits.length <= 8) {
        return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
    }
    if (digits.length <= 10) {
        return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4, 8)} ${digits.slice(8)}`;
    }

    return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4, 8)} ${digits.slice(8, 10)}:${digits.slice(10, 12)}`;
}

export function isValidDateTimeDisplay(display) {
    if (!display || !String(display).trim()) return true;

    return parseDateTimeDisplayToIso(display) !== '';
}

export function parseDateTimeDisplayToIso(display) {
    if (!display || !String(display).trim()) return '';

    const trimmed = String(display).trim();
    const [datePart, timePart = '00:00'] = trimmed.split(/\s+/);
    const rawDate = parseDateDisplayToRaw(datePart);

    if (!rawDate) return '';

    const timeDigits = digitsOnly(timePart).padEnd(4, '0').slice(0, 4);
    const hours = parseInt(timeDigits.slice(0, 2), 10);
    const minutes = parseInt(timeDigits.slice(2, 4), 10);

    if (hours > 23 || minutes > 59) return '';

    const [y, m, d] = rawDate.split('-').map(Number);
    const date = new Date(y, m - 1, d, hours, minutes, 0, 0);

    if (Number.isNaN(date.getTime())) return '';

    return date.toISOString();
}

/** Short label for chart axes: "04 Mar" or "Mar 2026" */
export function formatChartLabel(value) {
    if (!value) return '';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        const match = String(value).match(/^(\d{4})-(\d{2})/);
        if (match) {
            const d = new Date(parseInt(match[1], 10), parseInt(match[2], 10) - 1, 1);
            return d.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
        }
        return String(value);
    }

    if (String(value).includes('-') && String(value).length <= 10) {
        return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
    }

    return date.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
}
