import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const cards = [
    {
        href: 'admin.users.index',
        title: 'Manage Users',
        description: 'Roles, AI/Telegram access, password reset',
        className: 'bg-blue-50 border-blue-200 hover:bg-blue-100',
        titleClass: 'text-blue-900',
        descClass: 'text-blue-700',
    },
    {
        href: 'admin.ai-usage.index',
        title: 'AI Usage',
        description: 'Tokens, requests, top users',
        className: 'bg-indigo-50 border-indigo-200 hover:bg-indigo-100',
        titleClass: 'text-indigo-900',
        descClass: 'text-indigo-700',
    },
    {
        href: 'admin.telegram.index',
        title: 'Telegram',
        description: 'Linked accounts and unlink',
        className: 'bg-sky-50 border-sky-200 hover:bg-sky-100',
        titleClass: 'text-sky-900',
        descClass: 'text-sky-700',
    },
    {
        href: 'admin.audit.index',
        title: 'Audit Log',
        description: 'Transactions by source and user',
        className: 'bg-emerald-50 border-emerald-200 hover:bg-emerald-100',
        titleClass: 'text-emerald-900',
        descClass: 'text-emerald-700',
    },
];

export default function Dashboard() {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Admin Dashboard
                </h2>
            }
        >
            <Head title="Admin Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <h3 className="mb-4 text-lg font-semibold">Welcome to Admin Panel</h3>
                            <p className="mb-6 text-gray-600">
                                Monitor users, AI usage, Telegram links, and transaction audit trail.
                            </p>

                            <div className="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                                {cards.map((card) => (
                                    <Link
                                        key={card.href}
                                        href={route(card.href)}
                                        className={`rounded-lg border p-4 transition ${card.className}`}
                                    >
                                        <h4 className={`font-semibold ${card.titleClass}`}>
                                            {card.title}
                                        </h4>
                                        <p className={`text-sm ${card.descClass}`}>
                                            {card.description}
                                        </p>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
