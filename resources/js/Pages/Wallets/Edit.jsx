import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({ wallet }) {
    const { data, setData, put, processing, errors } = useForm({
        name: wallet.name,
        type: wallet.type,
        initial_balance: wallet.initial_balance,
        is_active: wallet.is_active,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('wallets.update', wallet.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit Wallet
                </h2>
            }
        >
            <Head title="Edit Wallet" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <Link
                        href={route('wallets.show', wallet.id)}
                        className="mb-4 inline-block text-sm text-indigo-600 hover:text-indigo-500"
                    >
                        ← Back
                    </Link>
                    <form onSubmit={handleSubmit} className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Name</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Type</label>
                            <select
                                value={data.type}
                                onChange={(e) => setData('type', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="bank">Bank</option>
                                <option value="ewallet">E-Wallet</option>
                                <option value="cash">Cash</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Initial Balance</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.initial_balance}
                                onChange={(e) => setData('initial_balance', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="0"
                            />
                            <p className="mt-1 text-xs text-gray-500">Changing this may require running Sync to recalculate balance.</p>
                            {errors.initial_balance && <p className="mt-1 text-sm text-red-600">{errors.initial_balance}</p>}
                        </div>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Update
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
