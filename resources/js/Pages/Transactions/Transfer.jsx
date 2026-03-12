import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Transfer({ wallets }) {
    const { data, setData, post, processing, errors } = useForm({
        from_wallet_id: wallets?.[0]?.id || '',
        to_wallet_id: wallets?.[1]?.id || '',
        amount: '',
        description: '',
        occurred_at: new Date().toISOString().slice(0, 16),
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('transactions.transfer'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Transfer
                </h2>
            }
        >
            <Head title="Transfer" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <Link
                        href={route('transactions.index')}
                        className="mb-4 inline-block text-sm text-indigo-600 hover:text-indigo-500"
                    >
                        ← Back to Transactions
                    </Link>
                    <form onSubmit={handleSubmit} className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">From Wallet</label>
                            <select
                                value={data.from_wallet_id}
                                onChange={(e) => setData('from_wallet_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Select wallet</option>
                                {wallets?.map((w) => (
                                    <option key={w.id} value={w.id}>{w.name}</option>
                                ))}
                            </select>
                            {errors.from_wallet_id && <p className="mt-1 text-sm text-red-600">{errors.from_wallet_id}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">To Wallet</label>
                            <select
                                value={data.to_wallet_id}
                                onChange={(e) => setData('to_wallet_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Select wallet</option>
                                {wallets?.map((w) => (
                                    <option key={w.id} value={w.id}>{w.name}</option>
                                ))}
                            </select>
                            {errors.to_wallet_id && <p className="mt-1 text-sm text-red-600">{errors.to_wallet_id}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Amount</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            {errors.amount && <p className="mt-1 text-sm text-red-600">{errors.amount}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Description</label>
                            <input
                                type="text"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Date & Time</label>
                            <input
                                type="datetime-local"
                                value={data.occurred_at}
                                onChange={(e) => setData('occurred_at', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Transfer
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
