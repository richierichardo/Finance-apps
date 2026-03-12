import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function formatPaginationLabel(label) {
    if (typeof label !== 'string') return label;
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}

export default function Index({ transactions, wallets, categories = [], filters = {} }) {
    const items = transactions?.data || [];
    const links = transactions?.links || [];
    const [walletId, setWalletId] = useState(filters.wallet_id || '');
    const [type, setType] = useState(filters.type || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [showBudgetModal, setShowBudgetModal] = useState(false);
    const [budgetCategoryId, setBudgetCategoryId] = useState('');
    const [budgetAmount, setBudgetAmount] = useState('');
    const [budgetPeriod, setBudgetPeriod] = useState('monthly');

    const applyFilters = () => {
        router.get(route('transactions.index'), {
            wallet_id: walletId || undefined,
            type: type || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setWalletId('');
        setType('');
        setDateFrom('');
        setDateTo('');
        router.get(route('transactions.index'));
    };

    const handleSetBudget = (e) => {
        e.preventDefault();
        router.post(route('budgets.store'), {
            category_id: budgetCategoryId,
            amount: budgetAmount,
            period: budgetPeriod,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setShowBudgetModal(false);
                setBudgetCategoryId('');
                setBudgetAmount('');
                setBudgetPeriod('monthly');
            },
            onError: (errors) => {
                console.error(errors);
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Transactions
                </h2>
            }
        >
            <Head title="Transactions" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="mb-4 flex flex-wrap items-end gap-2">
                        <Link
                            href={route('transactions.create')}
                            className="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Add Transaction
                        </Link>
                        <Link
                            href={route('transactions.transfer.form')}
                            className="inline-flex rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Transfer
                        </Link>
                        <button
                            type="button"
                            onClick={() => setShowBudgetModal(true)}
                            className="inline-flex rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Set Budget
                        </button>
                        <div className="ml-auto flex flex-wrap items-end gap-2">
                            <select
                                value={walletId}
                                onChange={(e) => setWalletId(e.target.value)}
                                className="rounded-md border-gray-300 text-sm"
                            >
                                <option value="">All Wallets</option>
                                {wallets?.map((w) => (
                                    <option key={w.id} value={w.id}>{w.name}</option>
                                ))}
                            </select>
                            <select
                                value={type}
                                onChange={(e) => setType(e.target.value)}
                                className="rounded-md border-gray-300 text-sm"
                            >
                                <option value="">All Types</option>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                                <option value="transfer_out">Transfer Out</option>
                                <option value="transfer_in">Transfer In</option>
                            </select>
                            <input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="rounded-md border-gray-300 text-sm"
                                placeholder="From"
                            />
                            <input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="rounded-md border-gray-300 text-sm"
                                placeholder="To"
                            />
                            <button
                                type="button"
                                onClick={applyFilters}
                                className="rounded-md bg-indigo-100 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-200"
                            >
                                Filter
                            </button>
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="overflow-x-auto">
                            {items.length === 0 ? (
                                <div className="p-6">
                                    <p className="text-gray-500">No transactions yet.</p>
                                </div>
                            ) : (
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ID</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Amount</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Wallet</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Category</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Source</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                                            <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {items.map((t) => (
                                            <tr key={t.id} className="hover:bg-gray-50">
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{t.id}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm capitalize text-gray-900">
                                                    {t.type}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                                                    {Number(t.amount).toLocaleString()}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                                    {t.wallet?.name ?? '-'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                                    {t.category_transaction ?? '-'}
                                                </td>
                                                <td className="max-w-[200px] truncate px-4 py-3 text-sm text-gray-500" title={t.description || ''}>
                                                    {t.description || '-'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                                    {t.source ?? '-'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                                    {t.occurred_at ? new Date(t.occurred_at).toLocaleString() : '-'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right text-sm">
                                                    <Link
                                                        href={route('transactions.show', t.id)}
                                                        className="font-medium text-indigo-600 hover:text-indigo-500"
                                                    >
                                                        View
                                                    </Link>
                                                    <span className="mx-1 text-gray-300">|</span>
                                                    <Link
                                                        href={route('transactions.edit', t.id)}
                                                        className="font-medium text-indigo-600 hover:text-indigo-500"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <span className="mx-1 text-gray-300">|</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            if (window.confirm(`Delete transaction "${t.description || t.type + ' - ' + Number(t.amount).toLocaleString()}"?`)) {
                                                                router.delete(route('transactions.destroy', t.id), { preserveScroll: true });
                                                            }
                                                        }}
                                                        className="font-medium text-red-600 hover:text-red-500"
                                                    >
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                        {links.length > 0 && (
                            <div className="flex items-center justify-center gap-1 border-t border-gray-200 bg-gray-50 px-4 py-3">
                                {links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url || '#'}
                                        className={`inline-flex min-w-[2.5rem] justify-center rounded px-3 py-1.5 text-sm ${
                                            link.active
                                                ? 'bg-indigo-600 font-medium text-white'
                                                : link.url
                                                    ? 'text-gray-700 hover:bg-gray-200'
                                                    : 'cursor-not-allowed text-gray-400'
                                        }`}
                                        preserveState
                                    >
                                        {formatPaginationLabel(link.label)}
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <Modal show={showBudgetModal} onClose={() => setShowBudgetModal(false)}>
                <form onSubmit={handleSetBudget} className="p-6">
                    <h3 className="text-lg font-medium text-gray-900">Set Budget</h3>
                    <p className="mt-1 text-sm text-gray-500">Create a budget for an expense category.</p>
                    <div className="mt-4 space-y-4">
                        <div>
                            <label htmlFor="budget_category" className="block text-sm font-medium text-gray-700">Category</label>
                            <select
                                id="budget_category"
                                required
                                value={budgetCategoryId}
                                onChange={(e) => setBudgetCategoryId(e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Select category</option>
                                {categories?.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label htmlFor="budget_amount" className="block text-sm font-medium text-gray-700">Budget Amount</label>
                            <input
                                id="budget_amount"
                                type="number"
                                required
                                min="0.01"
                                step="0.01"
                                value={budgetAmount}
                                onChange={(e) => setBudgetAmount(e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="e.g. 2000000"
                            />
                        </div>
                        <div>
                            <label htmlFor="budget_period" className="block text-sm font-medium text-gray-700">Period</label>
                            <select
                                id="budget_period"
                                value={budgetPeriod}
                                onChange={(e) => setBudgetPeriod(e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="monthly">Monthly</option>
                                <option value="weekly">Weekly</option>
                            </select>
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => setShowBudgetModal(false)}
                            className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Save Budget
                        </button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
