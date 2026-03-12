import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-900 dark:text-slate-200">
            {/* Background gradient - matches Welcome */}
            <div className="absolute inset-0 bg-gradient-to-br from-emerald-50 via-white to-teal-50 dark:from-slate-900 dark:via-slate-900 dark:to-emerald-950/30 pointer-events-none" />

            <div className="relative flex min-h-screen flex-col items-center justify-center px-6 py-12 sm:px-0">
                {/* Logo & branding */}
                <Link
                    href="/"
                    className="mb-8 flex items-center gap-2 transition opacity-90 hover:opacity-100"
                >
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/25">
                        <svg
                            className="h-7 w-7"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                    <span className="text-xl font-bold text-slate-800 dark:text-white">
                        Finance Tracker
                    </span>
                </Link>

                {/* Card container - matches Welcome feature cards */}
                <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white p-8 shadow-xl shadow-slate-200/50 ring-1 ring-slate-200/50 dark:bg-slate-800/90 dark:shadow-slate-900/50 dark:ring-slate-700/50">
                    {children}
                </div>

                {/* Back to home link */}
                <Link
                    href="/"
                    className="mt-6 text-sm text-slate-500 transition hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-500"
                >
                    ← Kembali ke beranda
                </Link>
            </div>
        </div>
    );
}
