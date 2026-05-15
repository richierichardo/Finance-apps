import {
    formatDateTimeDisplay,
    isValidDateTimeDisplay,
    maskDateTimeInput,
    parseDateTimeDisplayToIso,
} from '@/utils/date';
import { useEffect, useState } from 'react';

const inputClass =
    'block w-full rounded-xl border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export default function DateTimeInput({
    value = '',
    onChange,
    id,
    label,
    error,
    required = false,
    placeholder = 'dd/mm/yyyy HH:mm',
    className = '',
}) {
    const [displayValue, setDisplayValue] = useState(() => formatDateTimeDisplay(value));
    const [touched, setTouched] = useState(false);

    useEffect(() => {
        const formatted = formatDateTimeDisplay(value);
        setDisplayValue((prev) => (prev === formatted ? prev : formatted));
    }, [value]);

    const invalid = touched && displayValue && !isValidDateTimeDisplay(displayValue);

    const handleChange = (e) => {
        const masked = maskDateTimeInput(e.target.value);
        const iso = parseDateTimeDisplayToIso(masked);

        setDisplayValue(masked);
        if (iso) {
            onChange?.(iso);
        } else if (!masked.trim()) {
            onChange?.('');
        }
    };

    const handleBlur = () => {
        setTouched(true);
        if (displayValue && isValidDateTimeDisplay(displayValue)) {
            setDisplayValue(
                formatDateTimeDisplay(parseDateTimeDisplayToIso(displayValue)),
            );
        }
    };

    return (
        <div className={className}>
            {label && (
                <label htmlFor={id} className="block text-sm font-medium text-slate-700">
                    {label}
                </label>
            )}
            <input
                id={id}
                type="text"
                inputMode="numeric"
                autoComplete="off"
                required={required}
                placeholder={placeholder}
                value={displayValue}
                onChange={handleChange}
                onBlur={handleBlur}
                className={`${inputClass} ${label ? 'mt-1' : ''} ${
                    invalid || error ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-500' : ''
                }`}
            />
            {invalid && !error && (
                <p className="mt-1 text-xs text-rose-600">Use format dd/mm/yyyy HH:mm</p>
            )}
            {error && <p className="mt-1 text-sm text-rose-600">{error}</p>}
        </div>
    );
}
