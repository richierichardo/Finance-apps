import ActionIconButton from '@/Components/ActionIconButton';
import PageHeader from '@/Components/PageHeader';
import WalletTypeIcon from '@/Components/WalletTypeIcon';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatRupiah, walletTypeLabel } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ wallets }) {
    const totalBalance = wallets.reduce(
        (sum, wallet) => sum + Number(wallet.balance ?? 0),
        0,
    );
    const activeCount = wallets.filter((wallet) => wallet.is_active !== false).length;

    return (
        <AuthenticatedLayout>
            <Head title="Wallets" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Wallets"
                        subtitle="Manage your bank accounts, cash, and e-wallet balances."
                        actions={
                            <Link
                                href={route('wallets.create')}
                                className="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                            >
                                + Add Wallet
                            </Link>
                        }
                    />

                    <div className="mb-6 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm font-medium text-slate-500">Total Balance</p>
                                <p className="mt-1 text-2xl font-semibold text-slate-950">
                                    {formatRupiah(totalBalance)}
                                </p>
                            </div>
                            <div className="sm:text-right">
                                <p className="text-sm text-slate-500">
                                    <span className="font-semibold text-slate-900">{activeCount}</span>
                                    {' '}
                                    active wallet{activeCount === 1 ? '' : 's'}
                                </p>
                                <button
                                    type="button"
                                    onClick={() => router.post(route('wallets.sync'))}
                                    className="mt-2 inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm hover:bg-slate-50"
                                    title="Recalculate balance from transaction history (repair only)"
                                >
                                    <svg
                                        className="me-1.5 h-3.5 w-3.5 text-slate-400"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        strokeWidth={1.5}
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"
                                        />
                                    </svg>
                                    Recalculate balance (repair)
                                </button>
                            </div>
                        </div>
                    </div>

                    {wallets.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center shadow-sm">
                            <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                <svg
                                    className="h-7 w-7"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    strokeWidth={1.5}
                                    stroke="currentColor"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3"
                                    />
                                </svg>
                            </div>
                            <h3 className="mt-4 text-lg font-semibold text-slate-950">No wallets yet</h3>
                            <p className="mt-2 text-sm text-slate-600">
                                Add your first wallet to start tracking balances across accounts.
                            </p>
                            <Link
                                href={route('wallets.create')}
                                className="mt-6 inline-flex rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                            >
                                Add Wallet
                            </Link>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {wallets.map((wallet) => (
                                <article
                                    key={wallet.id}
                                    className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:shadow-md sm:p-6"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex min-w-0 items-start gap-3">
                                            <WalletTypeIcon type={wallet.type} />
                                            <div className="min-w-0">
                                                <h3 className="truncate font-semibold text-slate-950">
                                                    {wallet.name}
                                                </h3>
                                                <span className="mt-1 inline-flex rounded-lg border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-600">
                                                    {walletTypeLabel(wallet.type)}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <p className="mt-5 text-2xl font-semibold text-slate-950">
                                        {formatRupiah(wallet.balance ?? 0)}
                                    </p>

                                    <div className="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                                        <ActionIconButton
                                            type="view"
                                            href={route('wallets.show', wallet.id)}
                                        />
                                        <ActionIconButton
                                            type="edit"
                                            href={route('wallets.edit', wallet.id)}
                                        />
                                        <Link
                                            href={route('transactions.index', { wallet_id: wallet.id })}
                                            className="text-sm font-medium text-slate-500 hover:text-slate-900"
                                        >
                                            Transactions
                                        </Link>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
