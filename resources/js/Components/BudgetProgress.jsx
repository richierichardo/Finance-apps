function formatCurrency(n) {
    return new Intl.NumberFormat('id-ID', {
        style: 'decimal',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(n ?? 0);
}

function getProgressBarColor(status) {
    switch (status) {
        case 'safe': return 'bg-green-500';
        case 'warning': return 'bg-amber-500';
        case 'exceeded': return 'bg-red-500';
        default: return 'bg-gray-400';
    }
}

export default function BudgetProgress({ budgets = [] }) {
    if (!budgets || budgets.length === 0) {
        return (
            <div className="rounded-lg bg-white p-6 shadow">
                <h3 className="mb-4 text-lg font-medium text-gray-800">Budget Progress</h3>
                <p className="text-gray-400">No budgets set</p>
            </div>
        );
    }

    return (
        <div className="rounded-lg bg-white p-6 shadow">
            <h3 className="mb-4 text-lg font-medium text-gray-800">Budget Progress</h3>
            <div className="space-y-6">
                {budgets.map((item) => (
                    <div key={item.budget_id ?? item.category} className="space-y-2">
                        <div className="flex items-center justify-between text-sm">
                            <span className="font-medium text-gray-800">{item.category}</span>
                            <span className="text-gray-500">
                                {formatCurrency(item.spent)} / {formatCurrency(item.budget_amount)}
                            </span>
                        </div>
                        <div className="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                            <div
                                className={`h-full rounded-full transition-all ${getProgressBarColor(item.status)}`}
                                style={{ width: `${Math.min(item.percentage ?? 0, 100)}%` }}
                            />
                        </div>
                        <div className="flex justify-between text-xs text-gray-500">
                            <span>Remaining: {formatCurrency(item.remaining)}</span>
                            <span className={
                                item.status === 'safe' ? 'text-green-600' :
                                item.status === 'warning' ? 'text-amber-600' :
                                item.status === 'exceeded' ? 'text-red-600' : ''
                            }>
                                {item.percentage}% {item.status}
                            </span>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
