import CategoryIcon from '@/Components/CategoryIcon';
import { formatRupiah } from '@/utils/format';
import { parseCurrencyToNumber } from '@/utils/currency';

function dailyEstimate(amount, period) {
    const num = parseCurrencyToNumber(amount);

    if (!num) return null;

    if (period === 'monthly') return num / 30;
    if (period === 'weekly') return num / 7;
    if (period === 'yearly') return num / 365;

    return null;
}

export default function BudgetPreviewCard({
    categories = [],
    categoryId,
    amount,
    period,
}) {
    const category = categories.find((c) => String(c.id) === String(categoryId));
    const amountNum = parseCurrencyToNumber(amount);
    const daily = dailyEstimate(amount, period);

    const periodLabel =
        period === 'weekly' ? 'Weekly' : period === 'yearly' ? 'Yearly' : 'Monthly';

    return (
        <div className="rounded-xl border border-slate-100 bg-slate-50 p-5">
            <h4 className="text-sm font-semibold text-slate-950">Budget Preview</h4>

            <div className="mt-4 flex items-start gap-3">
                <CategoryIcon name={category?.name} slug={category?.slug} />
                <div className="min-w-0">
                    <p className="text-sm font-medium text-slate-900">
                        {category?.name ?? 'Select a category'}
                    </p>
                    <p className="mt-0.5 text-xs text-slate-500">{periodLabel} budget</p>
                </div>
            </div>

            <div className="mt-4 space-y-2 text-sm">
                <div className="flex justify-between gap-2">
                    <span className="text-slate-500">Budget amount</span>
                    <span className="font-semibold text-slate-900">
                        {amountNum > 0 ? formatRupiah(amountNum) : '—'}
                    </span>
                </div>
                {daily !== null && (
                    <div className="flex justify-between gap-2">
                        <span className="text-slate-500">Est. daily limit</span>
                        <span className="font-medium text-indigo-700">{formatRupiah(daily)}</span>
                    </div>
                )}
            </div>

            <p className="mt-4 text-xs text-slate-500">
                This is an estimated spending limit based on the selected period.
            </p>
        </div>
    );
}
