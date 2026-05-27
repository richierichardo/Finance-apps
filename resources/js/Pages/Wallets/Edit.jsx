import CurrencyInput from '@/Components/CurrencyInput';
import WalletPreviewCard from '@/Components/WalletPreviewCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { normalizeCurrencyRaw } from '@/utils/currency';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass =
    'mt-1 block w-full rounded-xl border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export default function Edit({ wallet }) {
    const { data, setData, put, processing, errors } = useForm({
        name: wallet.name,
        type: wallet.type,
        initial_balance: normalizeCurrencyRaw(wallet.initial_balance),
        is_active: wallet.is_active,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('wallets.update', wallet.id));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Edit Wallet" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Link
                        href={route('wallets.show', wallet.id)}
                        className="inline-flex items-center text-sm text-slate-500 hover:text-slate-900"
                    >
                        ← Back
                    </Link>

                    <div className="mt-4">
                        <h1 className="text-2xl font-semibold text-slate-950">Edit Wallet</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Update wallet details and initial balance.
                        </p>
                    </div>

                    <div className="mt-8 lg:grid lg:grid-cols-3 lg:gap-8">
                        <form
                            onSubmit={handleSubmit}
                            className="space-y-5 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm lg:col-span-2"
                        >
                            <div>
                                <label className="block text-sm font-medium text-slate-700">Name</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className={inputClass}
                                />
                                {errors.name && (
                                    <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                                )}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700">Type</label>
                                <select
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                    className={inputClass}
                                >
                                    <option value="bank">Bank</option>
                                    <option value="ewallet">E-Wallet</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700">
                                    Initial Balance
                                </label>
                                <CurrencyInput
                                    id="initial_balance"
                                    value={data.initial_balance}
                                    onChange={(raw) => setData('initial_balance', raw)}
                                    error={errors.initial_balance}
                                />
                                <div className="mt-3 flex gap-2 rounded-xl border border-amber-100 bg-amber-50 p-3 text-sm text-amber-700">
                                    <svg
                                        className="mt-0.5 h-4 w-4 shrink-0"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        strokeWidth={1.5}
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                                        />
                                    </svg>
                                    <p>
                                        Changing the initial balance may affect the calculated wallet balance.
                                        Balance updates automatically. Use Repair sync on Wallets only if totals look wrong.
                                    </p>
                                </div>
                                {errors.initial_balance && (
                                    <p className="mt-1 text-sm text-red-600">{errors.initial_balance}</p>
                                )}
                            </div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Update
                            </button>
                        </form>

                        <div className="mt-8 lg:mt-0">
                            <WalletPreviewCard
                                name={data.name}
                                type={data.type}
                                balance={wallet.balance}
                                label="Current wallet"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
