import ActionIconButton from '@/Components/ActionIconButton';
import CurrencyInput from '@/Components/CurrencyInput';
import DateInput from '@/Components/DateInput';
import EmptyState from '@/Components/EmptyState';
import RecurringPreviewCard from '@/Components/RecurringPreviewCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { cleanCurrencyValue, normalizeCurrencyRaw } from '@/utils/currency';
import { formatRupiah } from '@/utils/format';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const fieldClass =
    'mt-1 block w-full rounded-xl border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export default function Index({
    recurringTransactions = [],
    wallets = [],
    categories = [],
}) {
    const [showModal, setShowModal] = useState(false);
    const [showViewModal, setShowViewModal] = useState(false);
    const [viewingRecurring, setViewingRecurring] = useState(null);
    const [editingId, setEditingId] = useState(null);
    const [form, setForm] = useState({
        wallet_id: '',
        category_id: '',
        type: 'expense',
        amount: '',
        description: '',
        frequency: 'monthly',
        interval: '1',
        start_date: '',
        end_date: '',
    });

    const resetForm = () => {
        setForm({
            wallet_id: '',
            category_id: '',
            type: 'expense',
            amount: '',
            description: '',
            frequency: 'monthly',
            interval: '1',
            start_date: '',
            end_date: '',
        });
        setEditingId(null);
    };

    const openCreate = () => {
        resetForm();
        setShowModal(true);
    };

    const openEdit = (r) => {
        setForm({
            wallet_id: String(r.wallet_id),
            category_id: String(r.category_id),
            type: r.type,
            amount: normalizeCurrencyRaw(r.amount),
            description: r.description || '',
            frequency: r.frequency,
            interval: String(r.interval),
            start_date: r.start_date || '',
            end_date: r.end_date || '',
        });
        setEditingId(r.id);
        setShowModal(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const payload = {
            wallet_id: form.wallet_id,
            category_id: form.category_id,
            type: form.type,
            amount: parseFloat(cleanCurrencyValue(form.amount) || '0'),
            description: form.description || null,
            frequency: form.frequency,
            interval: parseInt(form.interval, 10) || 1,
            start_date: form.start_date,
            end_date: form.end_date || null,
        };

        if (editingId) {
            router.put(route('recurring-transactions.update', editingId), payload, {
                preserveScroll: true,
                onSuccess: () => {
                    setShowModal(false);
                    resetForm();
                },
            });
        } else {
            router.post(route('recurring-transactions.store'), payload, {
                preserveScroll: true,
                onSuccess: () => {
                    setShowModal(false);
                    resetForm();
                },
            });
        }
    };

    const handleDelete = (r) => {
        router.delete(route('recurring-transactions.destroy', r.id), {
            preserveScroll: true,
        });
    };

    const handleToggle = (r) => {
        router.post(route('recurring-transactions.toggle', r.id), {}, {
            preserveScroll: true,
        });
    };

    const frequencyLabel = (f, i) => {
        const n = parseInt(i, 10) || 1;
        if (f === 'daily') return n === 1 ? 'Daily' : `Every ${n} days`;
        if (f === 'weekly') return n === 1 ? 'Weekly' : `Every ${n} weeks`;
        if (f === 'monthly') return n === 1 ? 'Monthly' : `Every ${n} months`;
        return f;
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Recurring Transactions
                </h2>
            }
        >
            <Head title="Recurring Transactions" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="mb-4">
                        <button
                            type="button"
                            onClick={openCreate}
                            className="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Create Recurring
                        </button>
                    </div>

                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="overflow-x-auto">
                            {recurringTransactions.length === 0 ? (
                                <EmptyState
                                    icon={
                                        <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                        </svg>
                                    }
                                    title="No recurring transactions yet"
                                    subtitle="Create recurring items like rent, subscriptions, or salary."
                                    primaryAction={
                                        <button
                                            type="button"
                                            onClick={openCreate}
                                            className="inline-flex rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                                        >
                                            Create Recurring
                                        </button>
                                    }
                                />
                            ) : (
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Wallet</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Category</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Amount</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Frequency</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Next Run</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Active</th>
                                            <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {recurringTransactions.map((r) => (
                                            <tr key={r.id} className="hover:bg-gray-50">
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                                                    {r.description || '-'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                                    {r.wallet?.name ?? '-'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                                    {r.category?.name ?? '-'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm capitalize text-gray-500">
                                                    {r.type}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                                                    {formatRupiah(r.amount)}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                                    {frequencyLabel(r.frequency, r.interval)}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                                    {r.next_run_at
                                                        ? new Date(r.next_run_at).toLocaleDateString()
                                                        : '-'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3">
                                                    <button
                                                        type="button"
                                                        onClick={() => handleToggle(r)}
                                                        className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                                                            r.is_active ? 'bg-indigo-600' : 'bg-gray-200'
                                                        }`}
                                                    >
                                                        <span
                                                            className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition ${
                                                                r.is_active ? 'translate-x-5' : 'translate-x-1'
                                                            }`}
                                                        />
                                                    </button>
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right text-sm">
                                                    <div className="inline-flex items-center justify-end gap-1">
                                                        <ActionIconButton
                                                            type="view"
                                                            onClick={() => {
                                                                setViewingRecurring(r);
                                                                setShowViewModal(true);
                                                            }}
                                                        />
                                                        <ActionIconButton
                                                            type="edit"
                                                            onClick={() => openEdit(r)}
                                                        />
                                                        <ActionIconButton
                                                            type="delete"
                                                            confirmMessage={`Delete recurring "${r.description || 'Recurring'}"?`}
                                                            onClick={() => handleDelete(r)}
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <Modal show={showViewModal} onClose={() => { setShowViewModal(false); setViewingRecurring(null); }}>
                {viewingRecurring && (
                    <div className="p-6">
                        <h3 className="text-lg font-medium text-gray-900">Recurring Transaction Details</h3>
                        <dl className="mt-4 space-y-3">
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Description</dt>
                                <dd className="mt-1 text-sm text-gray-900">{viewingRecurring.description || '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Wallet</dt>
                                <dd className="mt-1 text-sm text-gray-900">{viewingRecurring.wallet?.name ?? '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Category</dt>
                                <dd className="mt-1 text-sm text-gray-900">{viewingRecurring.category?.name ?? '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Type</dt>
                                <dd className="mt-1 text-sm text-gray-900 capitalize">{viewingRecurring.type}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Amount</dt>
                                <dd className="mt-1 text-sm text-gray-900">{formatRupiah(viewingRecurring.amount)}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Frequency</dt>
                                <dd className="mt-1 text-sm text-gray-900">{frequencyLabel(viewingRecurring.frequency, viewingRecurring.interval)}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Next Run</dt>
                                <dd className="mt-1 text-sm text-gray-900">
                                    {viewingRecurring.next_run_at ? new Date(viewingRecurring.next_run_at).toLocaleDateString() : '-'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500">Active</dt>
                                <dd className="mt-1 text-sm text-gray-900">{viewingRecurring.is_active ? 'Yes' : 'No'}</dd>
                            </div>
                        </dl>
                        <div className="mt-6 flex justify-end">
                            <button
                                type="button"
                                onClick={() => { setShowViewModal(false); setViewingRecurring(null); }}
                                className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                )}
            </Modal>

            <Modal
                show={showModal}
                onClose={() => {
                    setShowModal(false);
                    resetForm();
                }}
                maxWidth="3xl"
                scrollable
            >
                <form onSubmit={handleSubmit} className="flex flex-col">
                    <div className="border-b border-slate-100 px-6 py-4">
                        <h3 className="text-lg font-semibold text-slate-950">
                            {editingId ? 'Edit Recurring Transaction' : 'Create Recurring Transaction'}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            Set up recurring income or expenses (e.g. Rent, Netflix, Salary).
                        </p>
                    </div>
                    <div className="flex-1 overflow-y-auto px-6 py-4">
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Wallet</label>
                                    <select
                                        required
                                        value={form.wallet_id}
                                        onChange={(e) => setForm((f) => ({ ...f, wallet_id: e.target.value }))}
                                        className={fieldClass}
                                    >
                                        <option value="">Select wallet</option>
                                        {wallets?.map((w) => (
                                            <option key={w.id} value={w.id}>{w.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Category</label>
                                    <select
                                        required
                                        value={form.category_id}
                                        onChange={(e) => setForm((f) => ({ ...f, category_id: e.target.value }))}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Select category</option>
                                        {categories?.map((c) => (
                                            <option key={c.id} value={c.id}>{c.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Type</label>
                                    <select
                                        required
                                        value={form.type}
                                        onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="expense">Expense</option>
                                        <option value="income">Income</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-700">Amount</label>
                                    <CurrencyInput
                                        value={form.amount}
                                        onChange={(raw) => setForm((f) => ({ ...f, amount: raw }))}
                                        required
                                    />
                                </div>
                            </div>

                            <RecurringPreviewCard
                                type={form.type}
                                amount={form.amount}
                                frequency={form.frequency}
                                interval={form.interval}
                                startDate={form.start_date}
                            />

                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Frequency</label>
                                    <select
                                        required
                                        value={form.frequency}
                                        onChange={(e) => setForm((f) => ({ ...f, frequency: e.target.value }))}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Interval</label>
                                    <input
                                        type="number"
                                        required
                                        min="1"
                                        value={form.interval}
                                        onChange={(e) => setForm((f) => ({ ...f, interval: e.target.value }))}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <DateInput
                                    id="start_date"
                                    label="Start Date"
                                    value={form.start_date}
                                    onChange={(raw) => setForm((f) => ({ ...f, start_date: raw }))}
                                    required
                                />
                                <DateInput
                                    id="end_date"
                                    label="End Date (optional)"
                                    value={form.end_date}
                                    onChange={(raw) => setForm((f) => ({ ...f, end_date: raw }))}
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">Description</label>
                                <input
                                    type="text"
                                    value={form.description}
                                    onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="e.g. Netflix subscription"
                                />
                            </div>
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 border-t border-slate-100 bg-white px-6 py-4">
                        <button
                            type="button"
                            onClick={() => {
                                setShowModal(false);
                                resetForm();
                            }}
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        >
                            {editingId ? 'Update' : 'Create'}
                        </button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
