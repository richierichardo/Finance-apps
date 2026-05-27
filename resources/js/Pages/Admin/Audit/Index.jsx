import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ transactions, filters, users }) {
    const [userId, setUserId] = useState(filters.user_id || '');
    const [source, setSource] = useState(filters.source || '');
    const [type, setType] = useState(filters.type || '');

    const applyFilters = (e) => {
        e.preventDefault();
        router.get(route('admin.audit.index'), {
            user_id: userId || undefined,
            source: source || undefined,
            type: type || undefined,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">Audit Log</h2>
            }
        >
            <Head title="Audit Log" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <Link
                        href={route('admin.dashboard')}
                        className="text-sm text-indigo-600 hover:text-indigo-800"
                    >
                        ← Admin dashboard
                    </Link>

                    <form
                        onSubmit={applyFilters}
                        className="flex flex-wrap items-end gap-4 rounded-lg bg-white p-4 shadow-sm"
                    >
                        <div>
                            <label className="block text-xs font-medium text-gray-500">User</label>
                            <select
                                value={userId}
                                onChange={(e) => setUserId(e.target.value)}
                                className="mt-1 rounded-md border-gray-300 text-sm"
                            >
                                <option value="">All</option>
                                {users.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500">Source</label>
                            <input
                                type="text"
                                value={source}
                                onChange={(e) => setSource(e.target.value)}
                                placeholder="web_manual"
                                className="mt-1 rounded-md border-gray-300 text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-500">Type</label>
                            <input
                                type="text"
                                value={type}
                                onChange={(e) => setType(e.target.value)}
                                placeholder="expense"
                                className="mt-1 rounded-md border-gray-300 text-sm"
                            />
                        </div>
                        <button
                            type="submit"
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700"
                        >
                            Filter
                        </button>
                    </form>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="overflow-x-auto p-6">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-gray-500">
                                        <th className="pb-2 pr-4">When</th>
                                        <th className="pb-2 pr-4">User</th>
                                        <th className="pb-2 pr-4">Type</th>
                                        <th className="pb-2 pr-4">Amount</th>
                                        <th className="pb-2 pr-4">Source</th>
                                        <th className="pb-2">Wallet</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {transactions.data.map((tx) => (
                                        <tr key={tx.id} className="border-b border-gray-100">
                                            <td className="py-3 pr-4 text-gray-500">
                                                {new Date(tx.occurred_at).toLocaleString('id-ID')}
                                            </td>
                                            <td className="py-3 pr-4">{tx.user?.email}</td>
                                            <td className="py-3 pr-4 capitalize">{tx.type}</td>
                                            <td className="py-3 pr-4">
                                                {Number(tx.amount).toLocaleString('id-ID')}
                                            </td>
                                            <td className="py-3 pr-4 font-mono text-xs">
                                                {tx.source}
                                            </td>
                                            <td className="py-3">{tx.wallet?.name}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
