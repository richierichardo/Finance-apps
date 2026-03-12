import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BudgetProgress from '@/Components/BudgetProgress';
import UpcomingRecurring from '@/Components/UpcomingRecurring';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
} from 'chart.js';
import { Bar, Doughnut, Line } from 'react-chartjs-2';
import { useCallback, useEffect, useState } from 'react';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend
);

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' },
    },
};

function formatCurrency(n) {
    return new Intl.NumberFormat('id-ID', {
        style: 'decimal',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(n);
}

export default function Dashboard() {
    const [summary, setSummary] = useState(null);
    const [cashflow, setCashflow] = useState([]);
    const [categoryBreakdown, setCategoryBreakdown] = useState([]);
    const [walletDistribution, setWalletDistribution] = useState([]);
    const [dailyExpense, setDailyExpense] = useState([]);
    const [topExpenses, setTopExpenses] = useState([]);
    const [budgets, setBudgets] = useState([]);
    const [upcomingRecurring, setUpcomingRecurring] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [cashflowPeriod, setCashflowPeriod] = useState('monthly');

    const fetchDashboard = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const base = '/dashboard';
            const [summaryRes, cashflowRes, categoryRes, walletRes, dailyRes, topRes, budgetsRes, upcomingRes] = await Promise.all([
                axios.get(`${base}/summary`),
                axios.get(`${base}/cashflow`, { params: { period: cashflowPeriod } }),
                axios.get(`${base}/category-breakdown`),
                axios.get(`${base}/wallet-distribution`),
                axios.get(`${base}/daily-expense`),
                axios.get(`${base}/top-expenses`),
                axios.get(`${base}/budgets`),
                axios.get(`${base}/upcoming-recurring`),
            ]);
            setSummary(summaryRes.data);
            setCashflow(cashflowRes.data);
            setCategoryBreakdown(categoryRes.data);
            setWalletDistribution(walletRes.data);
            setDailyExpense(dailyRes.data);
            setTopExpenses(topRes.data);
            setBudgets(budgetsRes.data);
            setUpcomingRecurring(upcomingRes.data ?? []);
        } catch (err) {
            setError(err.response?.data?.message || err.message || 'Failed to load dashboard');
        } finally {
            setLoading(false);
        }
    }, [cashflowPeriod]);

    useEffect(() => {
        fetchDashboard();
    }, [fetchDashboard]);

    const cashflowChartData = {
        labels: cashflow.map((d) => d.date),
        datasets: [
            { label: 'Income', data: cashflow.map((d) => d.income), backgroundColor: 'rgba(34, 197, 94, 0.5)', borderColor: 'rgb(34, 197, 94)' },
            { label: 'Expense', data: cashflow.map((d) => d.expense), backgroundColor: 'rgba(239, 68, 68, 0.5)', borderColor: 'rgb(239, 68, 68)' },
        ],
    };

    const categoryChartData = {
        labels: categoryBreakdown.map((c) => c.category_name),
        datasets: [
            {
                data: categoryBreakdown.map((c) => c.total),
                backgroundColor: [
                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                    '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1',
                ],
            },
        ],
    };

    const walletChartData = {
        labels: walletDistribution.map((w) => w.wallet_name),
        datasets: [
            {
                data: walletDistribution.map((w) => w.balance),
                backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
            },
        ],
    };

    const dailyExpenseChartData = {
        labels: dailyExpense.map((d) => d.date),
        datasets: [
            { label: 'Expense', data: dailyExpense.map((d) => d.total_expense), borderColor: 'rgb(239, 68, 68)', backgroundColor: 'rgba(239, 68, 68, 0.1)', fill: true },
        ],
    };

    if (loading && !summary) {
        return (
            <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>}>
                <Head title="Dashboard" />
                <div className="flex min-h-[200px] items-center justify-center py-12">
                    <div className="text-gray-500">Loading dashboard...</div>
                </div>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {error && (
                        <div className="mb-4 rounded-md bg-red-50 p-4 text-red-700">
                            {error}
                            <button
                                type="button"
                                onClick={fetchDashboard}
                                className="ml-2 underline"
                            >
                                Retry
                            </button>
                        </div>
                    )}

                    {/* Summary Cards */}
                    {summary && (
                        <div className="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="rounded-lg bg-white p-4 shadow">
                                <p className="text-sm text-gray-500">Total Income (Month)</p>
                                <p className="text-xl font-semibold text-green-600">{formatCurrency(summary.total_income)}</p>
                            </div>
                            <div className="rounded-lg bg-white p-4 shadow">
                                <p className="text-sm text-gray-500">Total Expense (Month)</p>
                                <p className="text-xl font-semibold text-red-600">{formatCurrency(summary.total_expense)}</p>
                            </div>
                            <div className="rounded-lg bg-white p-4 shadow">
                                <p className="text-sm text-gray-500">Net Cashflow</p>
                                <p className={`text-xl font-semibold ${summary.net_cashflow >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                    {formatCurrency(summary.net_cashflow)}
                                </p>
                            </div>
                            <div className="rounded-lg bg-white p-4 shadow">
                                <p className="text-sm text-gray-500">Total Balance (Wallets)</p>
                                <p className="text-xl font-semibold text-gray-800">
                                    {formatCurrency(summary.wallet_balances?.reduce((s, w) => s + w.balance, 0) ?? 0)}
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Charts */}
                    <div className="grid gap-8 lg:grid-cols-2">
                        {/* Cashflow */}
                        <div className="rounded-lg bg-white p-6 shadow">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-800">Cashflow</h3>
                                <select
                                    value={cashflowPeriod}
                                    onChange={(e) => setCashflowPeriod(e.target.value)}
                                    className="rounded border-gray-300 text-sm"
                                >
                                    <option value="monthly">Monthly</option>
                                    <option value="daily">Daily</option>
                                </select>
                            </div>
                            <div className="h-64">
                                {cashflow.length > 0 ? (
                                    <Bar data={cashflowChartData} options={chartOptions} />
                                ) : (
                                    <div className="flex h-full items-center justify-center text-gray-400">No data</div>
                                )}
                            </div>
                        </div>

                        {/* Daily Expense */}
                        <div className="rounded-lg bg-white p-6 shadow">
                            <h3 className="mb-4 text-lg font-medium text-gray-800">Daily Expense (30 Days)</h3>
                            <div className="h-64">
                                {dailyExpense.length > 0 ? (
                                    <Line data={dailyExpenseChartData} options={chartOptions} />
                                ) : (
                                    <div className="flex h-full items-center justify-center text-gray-400">No data</div>
                                )}
                            </div>
                        </div>

                        {/* Category Breakdown */}
                        <div className="rounded-lg bg-white p-6 shadow">
                            <h3 className="mb-4 text-lg font-medium text-gray-800">Expense by Category</h3>
                            <div className="h-64">
                                {categoryBreakdown.length > 0 ? (
                                    <Doughnut data={categoryChartData} options={chartOptions} />
                                ) : (
                                    <div className="flex h-full items-center justify-center text-gray-400">No data</div>
                                )}
                            </div>
                        </div>

                        {/* Wallet Distribution */}
                        <div className="rounded-lg bg-white p-6 shadow">
                            <h3 className="mb-4 text-lg font-medium text-gray-800">Wallet Distribution</h3>
                            <div className="h-64">
                                {walletDistribution.length > 0 ? (
                                    <Doughnut data={walletChartData} options={chartOptions} />
                                ) : (
                                    <div className="flex h-full items-center justify-center text-gray-400">No wallets</div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Budget Progress & Upcoming Recurring */}
                    <div className="mt-8 grid gap-8 lg:grid-cols-2">
                        <BudgetProgress budgets={budgets} />
                        <UpcomingRecurring items={upcomingRecurring} />
                    </div>

                    {/* Top Expenses */}
                    <div className="mt-8 rounded-lg bg-white p-6 shadow">
                        <h3 className="mb-4 text-lg font-medium text-gray-800">Top 5 Expenses This Month</h3>
                        {topExpenses.length > 0 ? (
                            <ul className="divide-y divide-gray-200">
                                {topExpenses.map((t) => (
                                    <li key={t.id} className="flex items-center justify-between py-3">
                                        <div>
                                            <p className="font-medium text-gray-800">{t.description}</p>
                                            <p className="text-sm text-gray-500">{t.occurred_at} · {t.category_name} · {t.wallet_name}</p>
                                        </div>
                                        <p className="font-semibold text-red-600">{formatCurrency(t.amount)}</p>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-gray-400">No expenses this month</p>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
