import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateTime, formatTransactionAmount } from '@/utils/format';
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
                    <div className="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
                        <p className="text-slate-600 capitalize">Type: {transaction.type?.replace('_', ' ')}</p>
                        <p className={`mt-1 ${formatTransactionAmount(transaction.type, transaction.amount).className}`}>
                            Amount: {formatTransactionAmount(transaction.type, transaction.amount).text}
                        </p>
                        <p className="mt-1 text-slate-600">Wallet: {transaction.wallet?.name}</p>
                        {transaction.category_transaction && (
                            <p className="mt-1 text-slate-600">Category: {transaction.category_transaction}</p>
                        )}
                        <p className="mt-1 text-slate-600">Date: {formatDateTime(transaction.occurred_at)}</p>
                        {transaction.description && (
                            <p className="text-gray-600">Description: {transaction.description}</p>
                        )}
                        <Link
                            href={route('transactions.edit', transaction.id)}
                            className="mt-6 inline-flex rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        >
                            Edit
                        </Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
