import { Head, Link } from '@inertiajs/react';

export default function Welcome({ auth }) {
    return (
        <>
            <Head title="Flowlet - Manage your money with better flow." />
            <div className="min-h-screen overflow-hidden bg-[#061421] text-slate-100">
                <div className="absolute inset-0 pointer-events-none bg-[radial-gradient(circle_at_top,_rgba(16,185,129,0.18),_transparent_32%),radial-gradient(circle_at_right,_rgba(20,184,166,0.14),_transparent_24%),linear-gradient(135deg,#07111d_0%,#0b1727_56%,#061421_100%)]" />

                <div className="relative flex min-h-screen flex-col">
                    <header className="flex items-center justify-between px-6 py-6 lg:px-12">
                        <div className="flex items-center gap-3">
                            <img
                                src="/assets/logo%20dashboard.webp"
                                alt="Flowlet"
                                className="h-20 w-26 rounded-2xl bg-white/10 p-2 shadow-lg shadow-emerald-500/10 ring-1 ring-white/10 backdrop-blur"
                            />
                            <div>
                                <div className="text-xl font-semibold tracking-tight text-white">
                                    Flowlet
                                </div>
                                <div className="text-xs uppercase tracking-[0.28em] text-slate-400">
                                    Manage your money with better flow.
                                </div>
                            </div>
                        </div>

                        <nav className="flex items-center gap-3">
                            {auth?.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-xl bg-emerald-500 px-5 py-2.5 font-medium text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:ring-offset-2 focus:ring-offset-[#061421]"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="rounded-xl px-4 py-2.5 font-medium text-slate-300 transition hover:bg-white/8 hover:text-white"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="rounded-xl bg-emerald-500 px-5 py-2.5 font-medium text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:ring-offset-2 focus:ring-offset-[#061421]"
                                    >
                                        Daftar Gratis
                                    </Link>
                                </>
                            )}
                        </nav>
                    </header>

                    <main className="flex flex-1 items-center px-6 pb-16 pt-6 lg:px-12">
                        <div className="mx-auto grid w-full max-w-7xl items-center gap-14 lg:grid-cols-[1.1fr_0.9fr]">
                            <section>
                                <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-emerald-200 backdrop-blur">
                                    <span className="h-2 w-2 rounded-full bg-emerald-400" />
                                    AI-assisted personal finance tracker
                                </div>
                                <h1 className="mt-6 max-w-3xl text-5xl font-semibold tracking-tight text-white sm:text-6xl lg:text-7xl">
                                    Manage your money with better flow.
                                </h1>
                                <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-300 sm:text-xl">
                                    Flowlet helps you manage wallets, transactions, budgets, recurring payments, cashflow, and forecasting in one calm, intelligent dashboard.
                                </p>

                                {!auth?.user && (
                                    <div className="mt-10 flex flex-wrap gap-4">
                                        <Link
                                            href={route('register')}
                                            className="rounded-xl bg-emerald-500 px-7 py-4 text-base font-semibold text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:ring-offset-2 focus:ring-offset-[#061421]"
                                        >
                                            Start free
                                        </Link>
                                        <Link
                                            href={route('login')}
                                            className="rounded-xl border border-white/10 bg-white/5 px-7 py-4 text-base font-semibold text-white transition hover:bg-white/10"
                                        >
                                            Log in
                                        </Link>
                                    </div>
                                )}

                                <div className="mt-10 grid gap-4 sm:grid-cols-3">
                                    <div className="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur">
                                        <div className="text-sm text-slate-400">
                                            Wallets
                                        </div>
                                        <div className="mt-2 text-lg font-semibold text-white">
                                            Unified balances
                                        </div>
                                    </div>
                                    <div className="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur">
                                        <div className="text-sm text-slate-400">
                                            AI assistant
                                        </div>
                                        <div className="mt-2 text-lg font-semibold text-white">
                                            Smart guidance
                                        </div>
                                    </div>
                                    <div className="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur">
                                        <div className="text-sm text-slate-400">
                                            Forecast
                                        </div>
                                        <div className="mt-2 text-lg font-semibold text-white">
                                            Cashflow visibility
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section className="relative">
                                <div className="absolute -inset-6 rounded-[2rem] bg-emerald-400/10 blur-3xl" />
                                <div className="relative overflow-hidden rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/20 backdrop-blur-xl">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <div className="text-sm uppercase tracking-[0.24em] text-emerald-200/70">
                                                Flowlet dashboard
                                            </div>
                                            <div className="mt-2 text-2xl font-semibold text-white">
                                                Clear money, calm decisions
                                            </div>
                                        </div>
                                        <img
                                            src="/assets/logo%20dashboard.webp"
                                            alt="Flowlet logo"
                                            className="h-20 w-26 rounded-2xl bg-white/10 p-2 ring-1 ring-white/10"
                                        />
                                    </div>

                                    <div className="mt-6 grid gap-4">
                                        <div className="rounded-2xl border border-white/10 bg-[#0c1b2d]/90 p-5">
                                            <div className="flex items-center justify-between text-sm text-slate-400">
                                                <span>Monthly cashflow</span>
                                                <span className="text-emerald-300">+12.4%</span>
                                            </div>
                                            <div className="mt-4 h-40 rounded-xl bg-[linear-gradient(180deg,rgba(16,185,129,0.22),rgba(16,185,129,0.04))] p-4">
                                                <div className="h-full rounded-lg border border-dashed border-emerald-400/30 bg-[linear-gradient(135deg,rgba(6,20,33,0.2),rgba(16,185,129,0.08))]" />
                                            </div>
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="rounded-2xl border border-white/10 bg-[#0c1b2d]/90 p-5">
                                                <div className="text-sm text-slate-400">
                                                    Upcoming recurring
                                                </div>
                                                <div className="mt-2 text-lg font-semibold text-white">
                                                    6 payments due
                                                </div>
                                                <p className="mt-2 text-sm text-slate-400">
                                                    Track subscriptions and bills before they hit.
                                                </p>
                                            </div>
                                            <div className="rounded-2xl border border-white/10 bg-[#0c1b2d]/90 p-5">
                                                <div className="text-sm text-slate-400">
                                                    Forecast horizon
                                                </div>
                                                <div className="mt-2 text-lg font-semibold text-white">
                                                    30 days ahead
                                                </div>
                                                <p className="mt-2 text-sm text-slate-400">
                                                    See what your balances may look like next.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </main>

                    <footer className="py-6 text-center text-sm text-slate-400">
                        Flowlet - Manage your money with better flow.
                    </footer>
                </div>
            </div>
        </>
    );
}
