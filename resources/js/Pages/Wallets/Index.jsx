import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ wallets }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Wallets
                </h2>
            }
        >
            <Head title="Wallets" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <Link
                        href={route('wallets.create')}
                        className="mb-4 inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Add Wallet
                    </Link>
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {wallets.length === 0 ? (
                                <p className="text-gray-500">No wallets yet.</p>
                            ) : (
                                <ul className="divide-y divide-gray-200">
                                    {wallets.map((wallet) => (
                                        <li key={wallet.id} className="flex items-center justify-between py-4">
                                            <div>
                                                <Link
                                                    href={route('wallets.show', wallet.id)}
                                                    className="font-medium text-indigo-600 hover:text-indigo-500"
                                                >
                                                    {wallet.name}
                                                </Link>
                                                <p className="text-sm text-gray-500">
                                                    {wallet.type} · Balance: {Number(wallet.balance ?? 0).toLocaleString()}
                                                </p>
                                            </div>
                                            <div className="flex gap-2">
                                                <Link
                                                    href={route('wallets.edit', wallet.id)}
                                                    className="inline-flex rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                                >
                                                    Edit
                                                </Link>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
