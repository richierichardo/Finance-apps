export function formatCurrency(n) {
    return new Intl.NumberFormat('id-ID', {
        style: 'decimal',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(n) || 0);
}

export function formatRupiah(n) {
    return `Rp ${formatCurrency(n)}`;
}

export function formatDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(date);
}

export function walletTypeLabel(type) {
    const labels = {
        bank: 'Bank',
        ewallet: 'E-Wallet',
        cash: 'Cash',
    };

    return labels[String(type).toLowerCase()] ?? type;
}

export function formatTransactionAmount(type, amount) {
    const formatted = formatRupiah(amount);

    if (type === 'income') {
        return {
            text: `+${formatted}`,
            className: 'font-semibold text-emerald-600',
        };
    }

    if (type === 'expense') {
        return {
            text: `-${formatted}`,
            className: 'font-semibold text-rose-600',
        };
    }

    if (type === 'transfer_in' || type === 'transfer_out') {
        return {
            text: formatted,
            className: 'font-semibold text-indigo-600',
        };
    }

    return {
        text: formatted,
        className: 'font-semibold text-slate-900',
    };
}
