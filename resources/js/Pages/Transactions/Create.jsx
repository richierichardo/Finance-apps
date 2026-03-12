import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm } from "@inertiajs/react";
import DatePicker from "react-datepicker";
import "react-datepicker/dist/react-datepicker.css";

export default function Create({
    wallets,
    categoriesIncome = [],
    categoriesExpenses = [],
}) {
    const { data, setData, post, processing, errors } = useForm({
        wallet_id: wallets?.[0]?.id || "",
        type: "income",
        amount: "",
        category_transaction: "",
        description: "",
        // stored as ISO string, but we'll display with custom picker
        occurred_at: new Date().toISOString(),
    });

    const categories =
        data.type === "income" ? categoriesIncome : categoriesExpenses;

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route("transactions.store"));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Add Transaction
                    </h2>

                    <Link
                        href={route("transactions.index")}
                        className="mb-4 inline-block text-sm text-indigo-600 hover:text-indigo-500"
                    >
                        ← Back to Transactions
                    </Link>
                </div>
            }
        >
            <Head title="Add Transaction" />

            <div className="py-10">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <form
                        onSubmit={handleSubmit}
                        className="bg-white shadow-sm sm:rounded-lg p-6 md:grid md:grid-cols-2 md:gap-6 gap-y-6"
                    >
                        <div className="flex flex-col">
                            <label className="text-sm font-medium text-gray-700">
                                Wallet
                            </label>
                            <select
                                value={data.wallet_id}
                                onChange={(e) =>
                                    setData("wallet_id", e.target.value)
                                }
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Select wallet</option>
                                {wallets?.map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name}
                                    </option>
                                ))}
                            </select>
                            {errors.wallet_id && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.wallet_id}
                                </p>
                            )}
                        </div>

                        <div className="flex flex-col">
                            <label className="text-sm font-medium text-gray-700">
                                Type
                            </label>
                            <select
                                value={data.type}
                                onChange={(e) => {
                                    setData("type", e.target.value);
                                    setData("category_transaction", "");
                                }}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>

                        <div className="flex flex-col">
                            <label className="text-sm font-medium text-gray-700">
                                Category
                            </label>
                            <select
                                value={data.category_transaction}
                                onChange={(e) =>
                                    setData(
                                        "category_transaction",
                                        e.target.value,
                                    )
                                }
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Select category</option>
                                {categories?.map((cat) => (
                                    <option key={cat.value} value={cat.value}>
                                        {cat.label}
                                    </option>
                                ))}
                            </select>
                            {errors.category_transaction && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.category_transaction}
                                </p>
                            )}
                        </div>

                        <div className="flex flex-col">
                            <label className="text-sm font-medium text-gray-700">
                                Amount
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={data.amount}
                                onChange={(e) =>
                                    setData("amount", e.target.value)
                                }
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            {errors.amount && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.amount}
                                </p>
                            )}
                        </div>

                        <div className="flex flex-col">
                            <label className="text-sm font-medium text-gray-700">
                                Date & Time
                            </label>
                            <DatePicker
                                selected={
                                    data.occurred_at
                                        ? new Date(data.occurred_at)
                                        : null
                                }
                                onChange={(date) => {
                                    if (date)
                                        setData(
                                            "occurred_at",
                                            date.toISOString(),
                                        );
                                }}
                                showTimeSelect
                                timeFormat="HH:mm"
                                timeIntervals={15}
                                dateFormat="dd/MM/yyyy HH:mm"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            {errors.occurred_at && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.occurred_at}
                                </p>
                            )}
                        </div>

                        <div className="flex flex-col md:col-span-2">
                            <label className="text-sm font-medium text-gray-700">
                                Description
                            </label>
                            <input
                                type="text"
                                value={data.description}
                                onChange={(e) =>
                                    setData("description", e.target.value)
                                }
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div className="md:col-span-2 flex justify-end">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Create Transaction
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
