import CurrencyInput from '@/Components/CurrencyInput';
import DateTimeInput from '@/Components/DateTimeInput';
import TransferPreviewCard from '@/Components/TransferPreviewCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass =
    'mt-1 block w-full rounded-xl border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export default function Transfer({ wallets }) {
    const { data, setData, post, processing, errors } = useForm({
        from_wallet_id: wallets?.[0]?.id || '',
        to_wallet_id: wallets?.[1]?.id || '',
        amount: '',
        description: '',
        occurred_at: new Date().toISOString(),
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('transactions.transfer'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Transfer" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Link
                        href={route('transactions.index')}
                        className="inline-flex items-center text-sm text-slate-500 hover:text-slate-900"
                    >
                        ← Back to Transactions
                    </Link>

                    <div className="mt-4">
                        <h1 className="text-2xl font-semibold text-slate-950">Transfer</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Move funds between your wallets.
                        </p>
                    </div>

                    <div className="mt-8 lg:grid lg:grid-cols-2 lg:gap-8">
                        <form
                            onSubmit={handleSubmit}
                            className="space-y-5 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm"
                        >
                            <div>
                                <label className="block text-sm font-medium text-slate-700">
                                    From Wallet
                                </label>
                                <select
                                    value={data.from_wallet_id}
                                    onChange={(e) => setData('from_wallet_id', e.target.value)}
                                    className={inputClass}
                                >
                                    <option value="">Select wallet</option>
                                    {wallets?.map((w) => (
                                        <option key={w.id} value={w.id}>
                                            {w.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.from_wallet_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.from_wallet_id}</p>
                                )}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700">To Wallet</label>
                                <select
                                    value={data.to_wallet_id}
                                    onChange={(e) => setData('to_wallet_id', e.target.value)}
                                    className={inputClass}
                                >
                                    <option value="">Select wallet</option>
                                    {wallets?.map((w) => (
                                        <option key={w.id} value={w.id}>
                                            {w.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.to_wallet_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.to_wallet_id}</p>
                                )}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700">Amount</label>
                                <CurrencyInput
                                    id="transfer_amount"
                                    value={data.amount}
                                    onChange={(raw) => setData('amount', raw)}
                                    error={errors.amount}
                                    required
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700">
                                    Description
                                </label>
                                <input
                                    type="text"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    className={inputClass}
                                />
                            </div>
                            <DateTimeInput
                                id="occurred_at"
                                label="Date & Time"
                                value={data.occurred_at}
                                onChange={(iso) => setData('occurred_at', iso)}
                                error={errors.occurred_at}
                                required
                            />
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50 sm:w-auto"
                            >
                                Transfer
                            </button>
                        </form>

                        <div className="mt-8 lg:mt-0">
                            <TransferPreviewCard
                                wallets={wallets}
                                fromWalletId={data.from_wallet_id}
                                toWalletId={data.to_wallet_id}
                                amount={data.amount}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
