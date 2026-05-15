import WalletTypeIcon from '@/Components/WalletTypeIcon';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatRupiah, walletTypeLabel } from '@/utils/format';
import { Head, Link } from '@inertiajs/react';

export default function Show({ wallet }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-slate-800">
                    {wallet.name}
                </h2>
            }
        >
            <Head title={wallet.name} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Link
                        href={route('wallets.index')}
                        className="inline-flex items-center text-sm text-slate-500 hover:text-slate-900"
                    >
                        ← Back to Wallets
                    </Link>
                    <div className="mt-6 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
                        <div className="flex items-start gap-3">
                            <WalletTypeIcon type={wallet.type} />
                            <div>
                                <p className="text-sm text-slate-500">{walletTypeLabel(wallet.type)}</p>
                                <p className="mt-2 text-2xl font-semibold text-slate-950">
                                    {formatRupiah(wallet.balance)}
                                </p>
                            </div>
                        </div>
                        <Link
                            href={route('wallets.edit', wallet.id)}
                            className="mt-6 inline-flex rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                        >
                            Edit
                        </Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
