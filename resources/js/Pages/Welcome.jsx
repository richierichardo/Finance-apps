import { Head, Link } from '@inertiajs/react';

export default function Welcome({ auth }) {
    return (
        <>
            <Head title="Finance Tracker - Kelola Keuangan dengan Mudah" />
            <div className="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-900 dark:text-slate-200">
                {/* Hero gradient background */}
                <div className="absolute inset-0 bg-gradient-to-br from-emerald-50 via-white to-teal-50 dark:from-slate-900 dark:via-slate-900 dark:to-emerald-950/30 pointer-events-none" />

                <div className="relative flex min-h-screen flex-col">
                    {/* Header */}
                    <header className="flex items-center justify-between px-6 py-6 lg:px-12">
                        <div className="flex items-center gap-2">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/25">
                                <svg
                                    className="h-6 w-6"
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
                        </div>

                        <nav className="flex items-center gap-3">
                            {auth?.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-lg bg-emerald-600 px-5 py-2.5 font-medium text-white shadow-md shadow-emerald-600/25 transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="rounded-lg px-4 py-2.5 font-medium text-slate-700 transition hover:bg-slate-200/80 dark:text-slate-300 dark:hover:bg-slate-800"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="rounded-lg bg-emerald-600 px-5 py-2.5 font-medium text-white shadow-md shadow-emerald-600/25 transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                                    >
                                        Daftar Gratis
                                    </Link>
                                </>
                            )}
                        </nav>
                    </header>

                    {/* Hero Section */}
                    <main className="flex flex-1 flex-col items-center justify-center px-6 pb-20 pt-4 text-center lg:px-12">
                        <h1 className="max-w-3xl text-4xl font-bold leading-tight text-slate-900 dark:text-white sm:text-5xl lg:text-6xl">
                            Kelola Keuangan{' '}
                            <span className="text-emerald-600 dark:text-emerald-500">
                                Lebih Teratur
                            </span>
                        </h1>
                        <p className="mt-6 max-w-xl text-lg text-slate-600 dark:text-slate-400">
                            Lacak pemasukan, pengeluaran, dan transfer antar dompet. Satu aplikasi untuk mengatur keuangan pribadi Anda dengan mudah.
                        </p>

                        {!auth?.user && (
                            <div className="mt-10 flex flex-wrap justify-center gap-4">
                                <Link
                                    href={route('register')}
                                    className="rounded-xl bg-emerald-600 px-8 py-4 text-lg font-semibold text-white shadow-lg shadow-emerald-600/30 transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                                >
                                    Mulai Sekarang
                                </Link>
                                <Link
                                    href={route('login')}
                                    className="rounded-xl border-2 border-slate-300 px-8 py-4 text-lg font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:bg-slate-800"
                                >
                                    Sudah punya akun?
                                </Link>
                            </div>
                        )}

                        {/* Feature Cards */}
                        <div className="mt-20 grid w-full max-w-4xl gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="rounded-2xl bg-white p-6 shadow-xl shadow-slate-200/50 ring-1 ring-slate-200/50 transition hover:shadow-emerald-100 dark:bg-slate-800/80 dark:shadow-slate-900/50 dark:ring-slate-700/50 dark:hover:ring-emerald-500/20">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-400">
                                    <svg
                                        className="h-6 w-6"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                                        />
                                    </svg>
                                </div>
                                <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                                    Kelola Dompet
                                </h3>
                                <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">
                                    Buat beberapa dompet (bank, e-wallet, cash) dan pantau saldo masing-masing dalam satu tempat.
                                </p>
                            </div>

                            <div className="rounded-2xl bg-white p-6 shadow-xl shadow-slate-200/50 ring-1 ring-slate-200/50 transition hover:shadow-emerald-100 dark:bg-slate-800/80 dark:shadow-slate-900/50 dark:ring-slate-700/50 dark:hover:ring-emerald-500/20">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-teal-100 text-teal-600 dark:bg-teal-900/50 dark:text-teal-400">
                                    <svg
                                        className="h-6 w-6"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                                        />
                                    </svg>
                                </div>
                                <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                                    Catat Transaksi
                                </h3>
                                <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">
                                    Rekam pemasukan dan pengeluaran dengan deskripsi. Riwayat transaksi tersimpan rapi dan mudah dicari.
                                </p>
                            </div>

                            <div className="rounded-2xl bg-white p-6 shadow-xl shadow-slate-200/50 ring-1 ring-slate-200/50 transition hover:shadow-emerald-100 dark:bg-slate-800/80 dark:shadow-slate-900/50 dark:ring-slate-700/50 dark:hover:ring-emerald-500/20 sm:col-span-2 lg:col-span-1">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400">
                                    <svg
                                        className="h-6 w-6"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"
                                        />
                                    </svg>
                                </div>
                                <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                                    Transfer Antar Dompet
                                </h3>
                                <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">
                                    Pindahkan dana antar dompet dengan mudah. Saldo otomatis terupdate.
                                </p>
                            </div>
                        </div>
                    </main>

                    <footer className="py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                        Finance Tracker — Kelola keuangan pribadi dengan lebih baik
                    </footer>
                </div>
            </div>
        </>
    );
}
