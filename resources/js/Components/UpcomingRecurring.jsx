import { Link } from '@inertiajs/react';

function formatCurrency(n) {
    return new Intl.NumberFormat('id-ID', {
        style: 'decimal',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(n ?? 0);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export default function UpcomingRecurring({ items = [] }) {
    if (!items || items.length === 0) {
        return (
            <div className="rounded-lg bg-white p-6 shadow">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-gray-800">Upcoming Recurring</h3>
                    <Link
                        href={route('recurring-transactions.index')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                    >
                        Manage
                    </Link>
                </div>
                <p className="text-gray-400">No upcoming recurring transactions</p>
            </div>
        );
    }

    return (
        <div className="rounded-lg bg-white p-6 shadow">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-lg font-medium text-gray-800">Upcoming Recurring</h3>
                <Link
                    href={route('recurring-transactions.index')}
                    className="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                >
                    Manage
                </Link>
            </div>
            <ul className="divide-y divide-gray-200">
                {items.map((item, idx) => (
                    <li key={idx} className="flex items-center justify-between py-3">
                        <div>
                            <p className="font-medium text-gray-800">{item.description}</p>
                            <p className="text-sm text-gray-500">{formatDate(item.next_run_at)}</p>
                        </div>
                        <p className="font-semibold text-gray-900">{formatCurrency(item.amount)}</p>
                    </li>
                ))}
            </ul>
        </div>
    );
}
