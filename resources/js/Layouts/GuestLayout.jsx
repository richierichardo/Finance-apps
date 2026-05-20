import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="min-h-screen overflow-hidden bg-[#061421] text-slate-100">
            <div className="absolute inset-0 pointer-events-none bg-[radial-gradient(circle_at_top,_rgba(16,185,129,0.16),_transparent_35%),radial-gradient(circle_at_bottom_right,_rgba(20,184,166,0.12),_transparent_30%),linear-gradient(135deg,#07111d_0%,#0b1727_52%,#061421_100%)]" />

            <div className="relative flex min-h-screen flex-col items-center justify-center px-6 py-12 sm:px-0">
                <Link
                    href="/"
                    className="mb-8 flex items-center gap-3 transition opacity-90 hover:opacity-100"
                >
                    <div className="flex h-20 w-26 items-center justify-center bg-white/10 p-2 shadow-lg shadow-emerald-500/10 ring-2 ring-white/10 backdrop-blur">
                        <ApplicationLogo className="h-full w-full object-contain" />
                    </div>
                    <div>
                        <div className="text-xl font-semibold tracking-tight text-white">
                            Flowlet
                        </div>
                        <div className="text-xs uppercase tracking-[0.28em] text-slate-400">
                            Manage your money with better flow.
                        </div>
                    </div>
                </Link>

                <div className="w-full max-w-md overflow-hidden rounded-[1.75rem] border border-white/10 bg-white/5 p-8 shadow-2xl shadow-black/20 ring-1 ring-white/10 backdrop-blur-xl">
                    {children}
                </div>

                <Link
                    href="/"
                    className="mt-6 text-sm text-slate-400 transition hover:text-emerald-300"
                >
                    &larr; Kembali ke beranda
                </Link>
            </div>
        </div>
    );
}
