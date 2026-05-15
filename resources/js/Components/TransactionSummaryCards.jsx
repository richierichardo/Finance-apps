import { formatRupiah } from '@/utils/format';

function SummaryCard({ label, value, icon, valueClass = 'text-slate-950', iconBg = 'bg-slate-100 text-slate-600' }) {
    return (
        <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
            <div className={`flex h-10 w-10 items-center justify-center rounded-xl ${iconBg}`}>
                {icon}
            </div>
            <p className="mt-4 text-sm font-medium text-slate-500">{label}</p>
            <p className={`mt-1 text-2xl font-semibold ${valueClass}`}>{value}</p>
        </div>
    );
}

export default function TransactionSummaryCards({ summary = {}, hasActiveFilters = false }) {
    const totalIncome = Number(summary.total_income ?? 0);
    const totalExpense = Number(summary.total_expense ?? 0);
    const net = Number(summary.net_cashflow ?? totalIncome - totalExpense);
    const count = Number(summary.total_count ?? 0);

    const netClass =
        net >= 0 ? 'text-emerald-600' : 'text-rose-600';

    return (
        <div className="mb-6">
            {hasActiveFilters && (
                <p className="mb-3 text-xs text-slate-500">Summary for filtered results</p>
            )}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <SummaryCard
                    label="Total Income"
                    value={formatRupiah(totalIncome)}
                    valueClass="text-emerald-600"
                    iconBg="bg-emerald-50 text-emerald-600"
                    icon={
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    }
                />
                <SummaryCard
                    label="Total Expense"
                    value={formatRupiah(totalExpense)}
                    valueClass="text-rose-600"
                    iconBg="bg-rose-50 text-rose-600"
                    icon={
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 12h-15" />
                        </svg>
                    }
                />
                <SummaryCard
                    label="Net Cashflow"
                    value={formatRupiah(net)}
                    valueClass={netClass}
                    iconBg={net >= 0 ? 'bg-indigo-50 text-indigo-600' : 'bg-rose-50 text-rose-600'}
                    icon={
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                    }
                />
                <SummaryCard
                    label="Total Transactions"
                    value={count.toLocaleString('id-ID')}
                    iconBg="bg-slate-100 text-slate-600"
                    icon={
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12" />
                        </svg>
                    }
                />
            </div>
        </div>
    );
}
