import ActionIconButton from '@/Components/ActionIconButton';
import BudgetPreviewCard from '@/Components/BudgetPreviewCard';
import CategoryIcon from '@/Components/CategoryIcon';
import CurrencyInput from '@/Components/CurrencyInput';
import DateInput from '@/Components/DateInput';
import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import PageHeader from '@/Components/PageHeader';
import TransactionSummaryCards from '@/Components/TransactionSummaryCards';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { cleanCurrencyValue } from '@/utils/currency';
import {
    formatDateTime,
    formatRupiah,
    formatTransactionAmount,
} from '@/utils/format';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function typeBadgeClass(type) {
    if (type === 'income') return 'bg-emerald-50 text-emerald-700';
    if (type === 'expense') return 'bg-rose-50 text-rose-700';
    if (type?.includes('transfer')) return 'bg-indigo-50 text-indigo-700';

    return 'bg-slate-50 text-slate-700';
}

function formatPaginationLabel(label) {
    if (typeof label !== 'string') return label;
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}

function TypeIcon({ type }) {
    const className = 'h-3.5 w-3.5 shrink-0';

    if (type === 'income') {
        return (
            <svg className={className} fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3" />
            </svg>
        );
    }
    if (type === 'expense') {
        return (
            <svg className={className} fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18" />
            </svg>
        );
    }
    if (type?.includes('transfer')) {
        return (
            <svg className={className} fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
        );
    }

    return null;
}

const filterClass =
    'rounded-xl border-slate-300 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

const inputClass =
    'mt-1 block w-full rounded-xl border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export default function Index({
    transactions,
    wallets,
    categories = [],
    filters = {},
    summary = {},
}) {
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

    const hasActiveFilters = Boolean(
        filters.wallet_id || filters.type || filters.date_from || filters.date_to,
    );

    const applyFilters = () => {
        router.get(
            route('transactions.index'),
            {
                wallet_id: walletId || undefined,
                type: type || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            { preserveState: true },
        );
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
        router.post(
            route('budgets.store'),
            {
                category_id: budgetCategoryId,
                amount: cleanCurrencyValue(budgetAmount) || budgetAmount,
                period: budgetPeriod,
            },
            {
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
            },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Transactions" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Transactions"
                        subtitle="Track your income, expenses, transfers, and budget activity."
                        actions={
                            <Link
                                href={route('transactions.create')}
                                className="inline-flex rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                            >
                                Add Transaction
                            </Link>
                        }
                    />

                    <TransactionSummaryCards
                        summary={summary}
                        hasActiveFilters={hasActiveFilters}
                    />

                    <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href={route('transactions.transfer.form')}
                            className="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            Transfer
                        </Link>
                        <button
                            type="button"
                            onClick={() => setShowBudgetModal(true)}
                            className="inline-flex rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100"
                        >
                            Set Budget
                        </button>
                        </div>

                        <div className="flex flex-wrap items-end gap-2">
                            <select
                                value={walletId}
                                onChange={(e) => setWalletId(e.target.value)}
                                className={filterClass}
                            >
                                <option value="">All Wallets</option>
                                {wallets?.map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={type}
                                onChange={(e) => setType(e.target.value)}
                                className={filterClass}
                            >
                                <option value="">All Types</option>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                                <option value="transfer_out">Transfer Out</option>
                                <option value="transfer_in">Transfer In</option>
                            </select>
                            <DateInput
                                value={dateFrom}
                                onChange={setDateFrom}
                                className="w-36"
                            />
                            <DateInput
                                value={dateTo}
                                onChange={setDateTo}
                                className="w-36"
                            />
                            <button
                                type="button"
                                onClick={applyFilters}
                                className="rounded-xl bg-indigo-100 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-200"
                            >
                                Filter
                            </button>
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm hover:bg-slate-50"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            {items.length === 0 ? (
                                <EmptyState
                                    icon={
                                        <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V5.625c0-1.036.84-1.875 1.875-1.875h11.25c1.035 0 1.875.84 1.875 1.875v9.75z" />
                                        </svg>
                                    }
                                    title="No transactions yet"
                                    subtitle="Add your first transaction or transfer funds between wallets."
                                    primaryAction={
                                        <Link
                                            href={route('transactions.create')}
                                            className="inline-flex rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                                        >
                                            Add Transaction
                                        </Link>
                                    }
                                    secondaryAction={
                                        <Link
                                            href={route('transactions.transfer.form')}
                                            className="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                                        >
                                            Transfer
                                        </Link>
                                    }
                                />
                            ) : (
                                <table className="min-w-full divide-y divide-slate-200">
                                    <thead className="sticky top-0 z-10 bg-slate-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                                                ID
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                                                Type
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                                                Amount
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                                                Wallet
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                                                Category
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                                                Description
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                                                Source
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                                                Date
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200 bg-white">
                                        {items.map((t) => {
                                            const amountDisplay = formatTransactionAmount(
                                                t.type,
                                                t.amount,
                                            );

                                            return (
                                                <tr key={t.id} className="hover:bg-slate-50/70">
                                                    <td className="whitespace-nowrap px-4 py-3 text-xs text-slate-400">
                                                        {t.id}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-sm">
                                                        <span
                                                            className={`inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-xs font-medium capitalize ${typeBadgeClass(t.type)}`}
                                                        >
                                                            <TypeIcon type={t.type} />
                                                            {t.type?.replace(/_/g, ' ')}
                                                        </span>
                                                    </td>
                                                    <td
                                                        className={`whitespace-nowrap px-4 py-3 text-sm ${amountDisplay.className}`}
                                                    >
                                                        {amountDisplay.text}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                                                        {t.wallet?.name ?? '-'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                                                        {t.category_transaction ?? '-'}
                                                    </td>
                                                    <td
                                                        className="max-w-[200px] truncate px-4 py-3 text-sm text-slate-400"
                                                        title={t.description || ''}
                                                    >
                                                        {t.description || '—'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-sm">
                                                        <span className="inline-flex rounded-lg bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                                            {t.source ?? '—'}
                                                        </span>
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-500">
                                                        {formatDateTime(t.occurred_at)}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right text-sm">
                                                        <div className="inline-flex items-center justify-end gap-1">
                                                            <ActionIconButton
                                                                type="view"
                                                                href={route('transactions.show', t.id)}
                                                            />
                                                            <ActionIconButton
                                                                type="edit"
                                                                href={route('transactions.edit', t.id)}
                                                            />
                                                            <ActionIconButton
                                                                type="delete"
                                                                confirmMessage={`Delete transaction "${t.description || `${t.type} - ${formatRupiah(t.amount)}`}"?`}
                                                                onClick={() => {
                                                                    router.delete(
                                                                        route('transactions.destroy', t.id),
                                                                        { preserveScroll: true },
                                                                    );
                                                                }}
                                                            />
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            )}
                        </div>
                        {links.length > 0 && (
                            <div className="flex items-center justify-center gap-1 border-t border-slate-200 bg-slate-50 px-4 py-3">
                                {links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url || '#'}
                                        className={`inline-flex min-w-[2.5rem] justify-center rounded-xl px-3 py-1.5 text-sm ${
                                            link.active
                                                ? 'bg-indigo-600 font-medium text-white'
                                                : link.url
                                                  ? 'text-slate-700 hover:bg-slate-200'
                                                  : 'cursor-not-allowed text-slate-400'
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

            <Modal
                show={showBudgetModal}
                onClose={() => setShowBudgetModal(false)}
                maxWidth="4xl"
                scrollable
            >
                <form onSubmit={handleSetBudget} className="flex flex-col">
                    <div className="border-b border-slate-100 px-6 py-4">
                        <h3 className="text-lg font-semibold text-slate-950">Set Budget</h3>
                        <p className="mt-1 text-sm text-slate-600">
                            Create a budget for an expense category.
                        </p>
                        <p className="mt-2 text-sm text-slate-500">
                            Budgets are usually used for expense categories.
                        </p>
                    </div>
                    <div className="flex-1 overflow-y-auto px-6 py-4">
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div className="space-y-4">
                        <div>
                            <label
                                htmlFor="budget_category"
                                className="block text-sm font-medium text-slate-700"
                            >
                                Category
                            </label>
                            <div className="mt-1 flex items-center gap-2">
                            <CategoryIcon
                                name={categories.find((c) => String(c.id) === String(budgetCategoryId))?.name}
                                slug={categories.find((c) => String(c.id) === String(budgetCategoryId))?.slug}
                            />
                            <select
                                id="budget_category"
                                required
                                value={budgetCategoryId}
                                onChange={(e) => setBudgetCategoryId(e.target.value)}
                                className={`${inputClass} flex-1`}
                            >
                                <option value="">Select category</option>
                                {categories?.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                            </div>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700">
                                Budget Amount
                            </label>
                            <CurrencyInput
                                id="budget_amount"
                                value={budgetAmount}
                                onChange={setBudgetAmount}
                                required
                            />
                        </div>
                        <div>
                            <label
                                htmlFor="budget_period"
                                className="block text-sm font-medium text-slate-700"
                            >
                                Period
                            </label>
                            <select
                                id="budget_period"
                                value={budgetPeriod}
                                onChange={(e) => setBudgetPeriod(e.target.value)}
                                className={inputClass}
                            >
                                <option value="monthly">Monthly</option>
                                <option value="weekly">Weekly</option>
                            </select>
                        </div>
                    </div>
                    <BudgetPreviewCard
                        categories={categories}
                        categoryId={budgetCategoryId}
                        amount={budgetAmount}
                        period={budgetPeriod}
                    />
                    </div>
                    </div>
                    <div className="flex justify-end gap-2 border-t border-slate-100 bg-white px-6 py-4">
                        <button
                            type="button"
                            onClick={() => setShowBudgetModal(false)}
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        >
                            Save Budget
                        </button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
