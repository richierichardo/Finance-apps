import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Show({ user, roles, statuses }) {
    const { data, setData, patch, processing } = useForm({
        role: user.role,
        status: user.status,
        ai_enabled: user.ai_enabled,
        telegram_enabled: user.telegram_enabled,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('admin.users.update', user.id));
    };

    const sendResetLink = () => {
        if (!confirm(`Send password reset link to ${user.email}?`)) return;
        router.post(route('admin.users.hard-reset-password', user.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    User: {user.name}
                </h2>
            }
        >
            <Head title={`User ${user.name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                    <Link
                        href={route('admin.users.index')}
                        className="text-sm text-indigo-600 hover:text-indigo-800"
                    >
                        ← Back to users
                    </Link>

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-gray-500">Email</dt>
                                <dd className="font-medium text-gray-900">{user.email}</dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Username</dt>
                                <dd className="font-medium text-gray-900">{user.username}</dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Created</dt>
                                <dd className="font-medium text-gray-900">
                                    {new Date(user.created_at).toLocaleString('id-ID')}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Last login</dt>
                                <dd className="font-medium text-gray-900">
                                    {user.last_login_at
                                        ? new Date(user.last_login_at).toLocaleString('id-ID')
                                        : '—'}
                                </dd>
                            </div>
                        </dl>

                        {user.telegram_account && (
                            <p className="mt-4 text-sm text-gray-600">
                                Telegram: @{user.telegram_account.username || '—'} (last seen{' '}
                                {user.telegram_account.last_seen_at
                                    ? new Date(user.telegram_account.last_seen_at).toLocaleString('id-ID')
                                    : '—'}
                                )
                            </p>
                        )}
                    </div>

                    <form onSubmit={submit} className="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                        <h3 className="text-lg font-medium text-gray-900">Feature access</h3>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">Role</label>
                            <select
                                value={data.role}
                                onChange={(e) => setData('role', e.target.value)}
                                className="mt-1 rounded-md border-gray-300 shadow-sm"
                            >
                                {roles.map((r) => (
                                    <option key={r} value={r}>
                                        {r}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">Status</label>
                            <select
                                value={data.status}
                                onChange={(e) => setData('status', e.target.value)}
                                className="mt-1 rounded-md border-gray-300 shadow-sm"
                            >
                                {statuses.map((s) => (
                                    <option key={s} value={s}>
                                        {s}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={data.ai_enabled}
                                onChange={(e) => setData('ai_enabled', e.target.checked)}
                            />
                            AI enabled
                        </label>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={data.telegram_enabled}
                                onChange={(e) => setData('telegram_enabled', e.target.checked)}
                            />
                            Telegram enabled
                        </label>

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Save changes
                        </button>
                    </form>

                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <h3 className="text-sm font-medium text-amber-900">Password reset</h3>
                        <p className="mt-1 text-sm text-amber-800">
                            Send a password reset email so the user can set a new password.
                        </p>
                        <button
                            type="button"
                            onClick={sendResetLink}
                            className="mt-3 rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700"
                        >
                            Send reset link
                        </button>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
