import {
    formatDateDisplay,
    isValidDateDisplay,
    maskDateInput,
    parseDateDisplayToRaw,
} from '@/utils/date';
import { useEffect, useState } from 'react';

const inputClass =
    'block w-full rounded-xl border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export default function DateInput({
    value = '',
    onChange,
    id,
    label,
    error,
    required = false,
    placeholder = 'dd/mm/yyyy',
    className = '',
}) {
    const [displayValue, setDisplayValue] = useState(() => formatDateDisplay(value));
    const [touched, setTouched] = useState(false);

    useEffect(() => {
        const formatted = formatDateDisplay(value);
        setDisplayValue((prev) => (prev === formatted ? prev : formatted));
    }, [value]);

    const invalid = touched && displayValue && !isValidDateDisplay(displayValue);

    const handleChange = (e) => {
        const masked = maskDateInput(e.target.value);
        const raw = parseDateDisplayToRaw(masked);

        setDisplayValue(masked);
        onChange?.(raw);
    };

    const handleBlur = () => {
        setTouched(true);
        if (displayValue && isValidDateDisplay(displayValue)) {
            setDisplayValue(formatDateDisplay(parseDateDisplayToRaw(displayValue)));
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
                <p className="mt-1 text-xs text-rose-600">Use format dd/mm/yyyy</p>
            )}
            {error && <p className="mt-1 text-sm text-rose-600">{error}</p>}
        </div>
    );
}
