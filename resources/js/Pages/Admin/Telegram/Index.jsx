import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ accounts }) {
    const unlink = (id) => {
        if (!confirm('Unlink this Telegram account?')) return;
        router.post(route('admin.telegram.unlink', id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Telegram Accounts
                </h2>
            }
        >
            <Head title="Telegram Accounts" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <Link
                        href={route('admin.dashboard')}
                        className="mb-4 inline-block text-sm text-indigo-600 hover:text-indigo-800"
                    >
                        ← Admin dashboard
                    </Link>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="overflow-x-auto p-6">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-gray-500">
                                        <th className="pb-2 pr-4">User</th>
                                        <th className="pb-2 pr-4">Telegram</th>
                                        <th className="pb-2 pr-4">Linked</th>
                                        <th className="pb-2 pr-4">Last seen</th>
                                        <th className="pb-2 pr-4">Active</th>
                                        <th className="pb-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {accounts.data.map((account) => (
                                        <tr key={account.id} className="border-b border-gray-100">
                                            <td className="py-3 pr-4">
                                                {account.user ? (
                                                    <>
                                                        <div className="font-medium text-gray-900">
                                                            {account.user.name}
                                                        </div>
                                                        <div className="text-gray-500">
                                                            {account.user.email}
                                                        </div>
                                                    </>
                                                ) : (
                                                    <span className="text-gray-400">Unlinked</span>
                                                )}
                                            </td>
                                            <td className="py-3 pr-4">
                                                @{account.username || account.telegram_user_id}
                                            </td>
                                            <td className="py-3 pr-4 text-gray-500">
                                                {account.linked_at
                                                    ? new Date(account.linked_at).toLocaleString('id-ID')
                                                    : '—'}
                                            </td>
                                            <td className="py-3 pr-4 text-gray-500">
                                                {account.last_seen_at
                                                    ? new Date(account.last_seen_at).toLocaleString('id-ID')
                                                    : '—'}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {account.is_active && account.user_id ? 'Yes' : 'No'}
                                            </td>
                                            <td className="py-3">
                                                {account.user_id && (
                                                    <button
                                                        type="button"
                                                        onClick={() => unlink(account.id)}
                                                        className="text-rose-600 hover:text-rose-800"
                                                    >
                                                        Unlink
                                                    </button>
                                                )}
                                            </td>
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
