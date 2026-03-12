import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Show({ transaction }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Transaction
                </h2>
            }
        >
            <Head title="Transaction" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <Link
                        href={route('transactions.index')}
                        className="mb-4 inline-block text-sm text-indigo-600 hover:text-indigo-500"
                    >
                        ← Back to Transactions
                    </Link>
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                        <p className="text-gray-600">Type: {transaction.type}</p>
                        <p className="text-gray-600">Amount: {Number(transaction.amount).toLocaleString()}</p>
                        <p className="text-gray-600">Wallet: {transaction.wallet?.name}</p>
                        {transaction.category_transaction && (
                            <p className="text-gray-600">Category: {transaction.category_transaction}</p>
                        )}
                        <p className="text-gray-600">Date: {new Date(transaction.occurred_at).toLocaleString()}</p>
                        {transaction.description && (
                            <p className="text-gray-600">Description: {transaction.description}</p>
                        )}
                        <Link
                            href={route('transactions.edit', transaction.id)}
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
