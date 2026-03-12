import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Show({ wallet }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    {wallet.name}
                </h2>
            }
        >
            <Head title={wallet.name} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <Link
                        href={route('wallets.index')}
                        className="mb-4 inline-block text-sm text-indigo-600 hover:text-indigo-500"
                    >
                        ← Back to Wallets
                    </Link>
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                        <p className="text-gray-600">Type: {wallet.type}</p>
                        <p className="text-gray-600">Balance: {Number(wallet.balance).toLocaleString()}</p>
                        <Link
                            href={route('wallets.edit', wallet.id)}
                            className="mt-4 inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Edit
                        </Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
