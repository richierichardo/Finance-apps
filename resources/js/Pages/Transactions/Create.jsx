import CurrencyInput from '@/Components/CurrencyInput';
import DateTimeInput from '@/Components/DateTimeInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const fieldClass =
    'mt-1 block w-full rounded-xl border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

const labelClass = 'text-sm font-medium text-slate-700';

export default function Create({
    wallets,
    categoriesIncome = [],
    categoriesExpenses = [],
}) {
    const { data, setData, post, processing, errors } = useForm({
        wallet_id: wallets?.[0]?.id || '',
        type: 'income',
        amount: '',
        category_transaction: '',
        description: '',
        occurred_at: new Date().toISOString(),
    });

    const categories =
        data.type === 'income' ? categoriesIncome : categoriesExpenses;

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('transactions.store'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Add Transaction" />

            <div className="py-8">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <Link
                        href={route('transactions.index')}
                        className="inline-flex items-center text-sm text-slate-500 hover:text-slate-900"
                    >
                        ← Back to Transactions
                    </Link>

                    <div className="mt-4">
                        <h1 className="text-2xl font-semibold text-slate-950">Add Transaction</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Record income or expense for a wallet.
                        </p>
                    </div>

                    <form
                        onSubmit={handleSubmit}
                        className="mt-8 grid grid-cols-1 gap-6 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm md:grid-cols-2 md:gap-8"
                    >
                        <div className="flex flex-col">
                            <label className={labelClass}>Wallet</label>
                            <select
                                value={data.wallet_id}
                                onChange={(e) => setData('wallet_id', e.target.value)}
                                className={fieldClass}
                            >
                                <option value="">Select wallet</option>
                                {wallets?.map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name}
                                    </option>
                                ))}
                            </select>
                            {errors.wallet_id && (
                                <p className="mt-1 text-sm text-red-600">{errors.wallet_id}</p>
                            )}
                        </div>

                        <div className="flex flex-col">
                            <label className={labelClass}>Type</label>
                            <select
                                value={data.type}
                                onChange={(e) => {
                                    setData('type', e.target.value);
                                    setData('category_transaction', '');
                                }}
                                className={fieldClass}
                            >
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>

                        <div className="flex flex-col">
                            <label className={labelClass}>Category</label>
                            <select
                                value={data.category_transaction}
                                onChange={(e) =>
                                    setData('category_transaction', e.target.value)
                                }
                                className={fieldClass}
                            >
                                <option value="">Select category</option>
                                {categories?.map((cat) => (
                                    <option key={cat.value} value={cat.value}>
                                        {cat.label}
                                    </option>
                                ))}
                            </select>
                            {errors.category_transaction && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.category_transaction}
                                </p>
                            )}
                        </div>

                        <div className="flex flex-col">
                            <label className={labelClass}>Amount</label>
                            <CurrencyInput
                                id="amount"
                                value={data.amount}
                                onChange={(raw) => setData('amount', raw)}
                                error={errors.amount}
                                required
                                className="mt-1"
                            />
                        </div>

                        <div className="flex flex-col">
                            <DateTimeInput
                                id="occurred_at"
                                label="Date & Time"
                                value={data.occurred_at}
                                onChange={(iso) => setData('occurred_at', iso)}
                                error={errors.occurred_at}
                                required
                            />
                        </div>

                        <div className="hidden flex-col justify-end md:flex">
                            <p className="text-xs text-slate-500">
                                Use categories to keep your spending organized.
                            </p>
                        </div>

                        <div className="flex flex-col md:col-span-2">
                            <label className={labelClass}>Description</label>
                            <input
                                type="text"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className={fieldClass}
                                placeholder="Optional note"
                            />
                        </div>

                        <div className="flex justify-end md:col-span-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Create Transaction
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
