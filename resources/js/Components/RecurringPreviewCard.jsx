import { formatRupiah } from '@/utils/format';
import { parseCurrencyToNumber } from '@/utils/currency';

function frequencyText(frequency, interval) {
    const n = parseInt(interval, 10) || 1;

    if (frequency === 'daily') return n === 1 ? 'daily' : `every ${n} days`;
    if (frequency === 'weekly') return n === 1 ? 'weekly' : `every ${n} weeks`;
    if (frequency === 'monthly') return n === 1 ? 'monthly' : `every ${n} months`;

    return frequency;
}

export default function RecurringPreviewCard({
    type = 'expense',
    amount,
    frequency = 'monthly',
    interval = '1',
    startDate = '',
}) {
    const amountNum = parseCurrencyToNumber(amount);
    const typeLabel = type === 'income' ? 'Income' : 'Expense';
    const freqLabel = frequencyText(frequency, interval);

    const startLabel = startDate
        ? new Date(startDate).toLocaleDateString('id-ID', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
          })
        : 'the selected start date';

    return (
        <div className="rounded-xl border border-slate-100 bg-slate-50 p-4">
            <h4 className="text-sm font-semibold text-slate-950">Recurring Preview</h4>
            <p className="mt-2 text-sm text-slate-600">
                <span className={`font-medium ${type === 'income' ? 'text-emerald-700' : 'text-rose-700'}`}>
                    {typeLabel}
                </span>
                {' '}of{' '}
                <span className="font-semibold text-slate-900">
                    {amountNum > 0 ? formatRupiah(amountNum) : 'Rp 0'}
                </span>
                {' '}will repeat{' '}
                <span className="font-medium text-slate-900">{freqLabel}</span>
                {' '}starting from{' '}
                <span className="font-medium text-slate-900">{startLabel}</span>.
            </p>
        </div>
    );
}
