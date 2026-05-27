import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

function StatCard({ label, value }) {
    return (
        <div className="rounded-lg border border-gray-100 bg-gray-50 p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
            <p className="mt-1 text-xl font-semibold text-gray-900">{value}</p>
        </div>
    );
}

export default function Index({ summary, top_users, config_flags }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">AI Usage</h2>
            }
        >
            <Head title="AI Usage" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
                    <Link
                        href={route('admin.dashboard')}
                        className="text-sm text-indigo-600 hover:text-indigo-800"
                    >
                        ← Admin dashboard
                    </Link>

                    <section className="rounded-lg bg-white p-6 shadow-sm">
                        <h3 className="mb-4 text-lg font-medium text-gray-900">Today</h3>
                        <div className="grid gap-4 sm:grid-cols-4">
                            <StatCard label="Requests" value={summary.today.requests} />
                            <StatCard label="Total tokens" value={summary.today.total_tokens.toLocaleString()} />
                            <StatCard label="Prompt tokens" value={summary.today.prompt_tokens.toLocaleString()} />
                            <StatCard label="Avg latency (ms)" value={summary.today.avg_latency_ms} />
                        </div>
                    </section>

                    <section className="rounded-lg bg-white p-6 shadow-sm">
                        <h3 className="mb-4 text-lg font-medium text-gray-900">This month</h3>
                        <div className="grid gap-4 sm:grid-cols-4">
                            <StatCard label="Requests" value={summary.month.requests} />
                            <StatCard label="Total tokens" value={summary.month.total_tokens.toLocaleString()} />
                            <StatCard label="Prompt tokens" value={summary.month.prompt_tokens.toLocaleString()} />
                            <StatCard label="Avg latency (ms)" value={summary.month.avg_latency_ms} />
                        </div>
                    </section>

                    <section className="rounded-lg bg-white p-6 shadow-sm">
                        <h3 className="mb-4 text-lg font-medium text-gray-900">Top users (month)</h3>
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-gray-500">
                                    <th className="pb-2 pr-4">User</th>
                                    <th className="pb-2 pr-4">Requests</th>
                                    <th className="pb-2">Tokens</th>
                                </tr>
                            </thead>
                            <tbody>
                                {top_users.map((row) => (
                                    <tr key={row.user_id} className="border-b border-gray-50">
                                        <td className="py-2 pr-4">
                                            <div className="font-medium text-gray-900">{row.name}</div>
                                            <div className="text-gray-500">{row.email}</div>
                                        </td>
                                        <td className="py-2 pr-4">{row.requests}</td>
                                        <td className="py-2">{row.tokens.toLocaleString()}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>

                    <section className="rounded-lg bg-white p-6 shadow-sm">
                        <h3 className="mb-4 text-lg font-medium text-gray-900">Feature flags (env)</h3>
                        <dl className="grid gap-2 text-sm sm:grid-cols-2">
                            {Object.entries(config_flags).map(([key, value]) => (
                                <div key={key} className="flex justify-between gap-4 border-b border-gray-50 py-1">
                                    <dt className="text-gray-500">{key}</dt>
                                    <dd className="font-mono text-gray-900">{String(value)}</dd>
                                </div>
                            ))}
                        </dl>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
