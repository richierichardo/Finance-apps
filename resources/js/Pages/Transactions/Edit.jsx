import CurrencyInput from '@/Components/CurrencyInput';
import DateTimeInput from '@/Components/DateTimeInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { normalizeCurrencyRaw } from '@/utils/currency';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({ transaction, wallets, categoriesIncome = [], categoriesExpenses = [] }) {
    const typeValue = typeof transaction.type === 'object' && transaction.type?.value
        ? transaction.type.value
        : (transaction.type ?? '');
    const { data, setData, put, processing, errors } = useForm({
        wallet_id: transaction.wallet_id,
        type: typeValue,
        amount: normalizeCurrencyRaw(transaction.amount),
        category_transaction: (typeof transaction.category_transaction === 'object' && transaction.category_transaction?.value)
            ? transaction.category_transaction.value
            : (transaction.category_transaction || ''),
        description: transaction.description || '',
        occurred_at: transaction.occurred_at ? new Date(transaction.occurred_at).toISOString() : '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('transactions.update', transaction.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit Transaction
                </h2>
            }
        >
            <Head title="Edit Transaction" />

            <div className="py-10">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <Link
                        href={route('transactions.show', transaction.id)}
                        className="mb-4 inline-block text-sm text-indigo-600 hover:text-indigo-500"
                    >
                        ← Back to Transaction
                    </Link>
                    <form onSubmit={handleSubmit} className="grid grid-cols-2 gap-4 overflow-hidden bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Wallet</label>
                            <select
                                value={data.wallet_id}
                                onChange={(e) => setData('wallet_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                {wallets?.map((w) => (
                                    <option key={w.id} value={w.id}>{w.name}</option>
                                ))}
                            </select>
                            {errors.wallet_id && <p className="mt-1 text-sm text-red-600">{errors.wallet_id}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Type</label>
                            <select
                                value={data.type}
                                onChange={(e) => {
                                    setData('type', e.target.value);
                                    setData('category_transaction', '');
                                }}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                                <option value="transfer_out">Transfer Out</option>
                                <option value="transfer_in">Transfer In</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Category</label>
                            <select
                                value={data.category_transaction}
                                onChange={(e) => setData('category_transaction', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                disabled={data.type !== 'income' && data.type !== 'expense'}
                            >
                                <option value="">Select category</option>
                                {(data.type === 'income' ? categoriesIncome : data.type === 'expense' ? categoriesExpenses : [])?.map((cat) => (
                                    <option key={cat.value} value={cat.value}>{cat.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Amount</label>
                            <CurrencyInput
                                value={data.amount}
                                onChange={(raw) => setData('amount', raw)}
                                error={errors.amount}
                                required
                            />
                        </div>
                        <div>
                            <DateTimeInput
                                id="occurred_at"
                                label="Date & Time"
                                value={data.occurred_at}
                                onChange={(iso) => setData('occurred_at', iso)}
                                error={errors.occurred_at}
                                required
                            />
                        </div>
                        <div className="col-span-2">
                            <label className="block text-sm font-medium text-gray-700">Description</label>
                            <input
                                type="text"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Update Transaction
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
