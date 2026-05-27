import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ users, filters }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Manage Users
                </h2>
            }
        >
            <Head title="Manage Users" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="overflow-x-auto p-6">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-gray-500">
                                        <th className="pb-2 pr-4">Name</th>
                                        <th className="pb-2 pr-4">Email</th>
                                        <th className="pb-2 pr-4">Role</th>
                                        <th className="pb-2 pr-4">AI</th>
                                        <th className="pb-2 pr-4">Telegram</th>
                                        <th className="pb-2 pr-4">Last login</th>
                                        <th className="pb-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.data.map((user) => (
                                        <tr key={user.id} className="border-b border-gray-100">
                                            <td className="py-3 pr-4 font-medium text-gray-900">
                                                {user.name}
                                            </td>
                                            <td className="py-3 pr-4 text-gray-600">{user.email}</td>
                                            <td className="py-3 pr-4 capitalize">{user.role}</td>
                                            <td className="py-3 pr-4">
                                                {user.ai_enabled ? 'Yes' : 'No'}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {user.telegram_enabled ? 'Yes' : 'No'}
                                            </td>
                                            <td className="py-3 pr-4 text-gray-500">
                                                {user.last_login_at
                                                    ? new Date(user.last_login_at).toLocaleString('id-ID')
                                                    : '—'}
                                            </td>
                                            <td className="py-3">
                                                <Link
                                                    href={route('admin.users.show', user.id)}
                                                    className="text-indigo-600 hover:text-indigo-800"
                                                >
                                                    View
                                                </Link>
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
