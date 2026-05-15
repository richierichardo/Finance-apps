import {
    cleanCurrencyValue,
    formatCurrencyInputDisplay,
    normalizeCurrencyRaw,
} from '@/utils/currency';
import { useEffect, useState } from 'react';

const defaultInputClass =
    'block w-full rounded-xl border-slate-300 py-2.5 pl-10 pr-3 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export default function CurrencyInput({
    value = '',
    onChange,
    id,
    error,
    required = false,
    placeholder = '0',
    className = '',
    disabled = false,
}) {
    const [displayValue, setDisplayValue] = useState(() =>
        formatCurrencyInputDisplay(normalizeCurrencyRaw(value)),
    );

    useEffect(() => {
        const cleaned = normalizeCurrencyRaw(value);
        const formatted = formatCurrencyInputDisplay(cleaned);
        setDisplayValue((prev) => (prev === formatted ? prev : formatted));
    }, [value]);

    const handleChange = (e) => {
        const raw = cleanCurrencyValue(e.target.value);
        const formatted = formatCurrencyInputDisplay(raw);

        setDisplayValue(formatted);
        onChange?.(raw);
    };

    return (
        <div>
            <div className="relative">
                <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-medium text-slate-500">
                    Rp
                </span>
                <input
                    id={id}
                    type="text"
                    inputMode="numeric"
                    autoComplete="off"
                    required={required}
                    disabled={disabled}
                    value={displayValue}
                    onChange={handleChange}
                    placeholder={placeholder}
                    className={`${defaultInputClass} ${className}`}
                />
            </div>
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}
