import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: 'bank',
        initial_balance: 0,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('wallets.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Create Wallet
                </h2>
            }
        >
            <Head title="Create Wallet" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <Link
                        href={route('wallets.index')}
                        className="mb-4 inline-block text-sm text-indigo-600 hover:text-indigo-500"
                    >
                        ← Back to Wallets
                    </Link>
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-4">
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
                                    value={data.initial_balance}
                                    onChange={(e) => setData('initial_balance', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Create Wallet
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
