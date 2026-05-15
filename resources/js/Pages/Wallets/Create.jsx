import CurrencyInput from '@/Components/CurrencyInput';
import WalletPreviewCard from '@/Components/WalletPreviewCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass =
    'mt-1 block w-full rounded-xl border-slate-300 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: 'bank',
        initial_balance: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('wallets.store'), {
            transform: (formData) => ({
                ...formData,
                initial_balance:
                    formData.initial_balance === '' ? 0 : formData.initial_balance,
            }),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Create Wallet" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Link
                        href={route('wallets.index')}
                        className="inline-flex items-center text-sm text-slate-500 hover:text-slate-900"
                    >
                        ← Back to Wallets
                    </Link>

                    <div className="mt-4">
                        <h1 className="text-2xl font-semibold text-slate-950">Create Wallet</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            Add a new account to track your balance.
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
                            </div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Create Wallet
                            </button>
                        </form>

                        <div className="mt-8 lg:mt-0">
                            <WalletPreviewCard
                                name={data.name}
                                type={data.type}
                                balance={data.initial_balance}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
