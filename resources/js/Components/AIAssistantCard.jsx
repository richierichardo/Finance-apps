import axios from 'axios';
import { useEffect, useState } from 'react';

const quickPrompts = [
    'Ringkasan keuangan bulan ini',
    'Forecast bulan ini gimana?',
    'Saldo wallet saya berapa?',
    'Status budget bulan ini',
];

export default function AIAssistantCard() {
    const [message, setMessage] = useState('');
    const [loading, setLoading] = useState(false);
    const [response, setResponse] = useState(null);
    const [error, setError] = useState(null);
    const [usage, setUsage] = useState(null);

    useEffect(() => {
        axios
            .get(route('ai.usage-summary'))
            .then(({ data }) => setUsage(data))
            .catch(() => setUsage(null));
    }, [response]);

    const sendMessage = async (text) => {
        const msg = (text ?? message).trim();
        if (!msg) return;

        setLoading(true);
        setError(null);

        try {
            const { data } = await axios.post(route('ai.chat'), { message: msg });
            setResponse(data);
            if (!text) setMessage('');
        } catch (err) {
            setError(err.response?.data?.message || 'Gagal menghubungi AI Assistant.');
            setResponse(null);
        } finally {
            setLoading(false);
        }
    };

    const handleConfirm = async () => {
        if (!response?.draft_id) return;
        setLoading(true);
        try {
            const { data } = await axios.post(
                route('ai.action.confirm', response.draft_id),
            );
            setResponse(data);
        } catch (err) {
            setError(err.response?.data?.message || 'Konfirmasi gagal.');
        } finally {
            setLoading(false);
        }
    };

    const handleCancel = async () => {
        if (!response?.draft_id) return;
        setLoading(true);
        try {
            const { data } = await axios.post(
                route('ai.action.cancel', response.draft_id),
            );
            setResponse(data);
        } catch (err) {
            setError(err.response?.data?.message || 'Pembatalan gagal.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-lg font-semibold text-slate-950">AI Assistant</h3>
                    <p className="mt-1 text-sm text-slate-600">
                        Ask about cashflow, budget, wallet balance, or forecast.
                    </p>
                    <p className="mt-2 text-xs text-slate-500">
                        Bantuan pencatatan dan analisis sederhana — bukan nasihat finansial profesional.
                    </p>
                </div>
                <span className="rounded-lg bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">
                    Qwen · Beta
                </span>
            </div>

            {usage && (
                <div className="mt-4 grid grid-cols-2 gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs text-slate-600 sm:grid-cols-4">
                    <div>
                        <p className="font-medium text-slate-500">Today tokens</p>
                        <p className="text-slate-900">{usage.today?.total_tokens?.toLocaleString() ?? 0}</p>
                    </div>
                    <div>
                        <p className="font-medium text-slate-500">Month tokens</p>
                        <p className="text-slate-900">{usage.this_month?.total_tokens?.toLocaleString() ?? 0}</p>
                    </div>
                    <div>
                        <p className="font-medium text-slate-500">Requests (month)</p>
                        <p className="text-slate-900">{usage.this_month?.requests ?? 0}</p>
                    </div>
                    <div>
                        <p className="font-medium text-slate-500">Avg / request</p>
                        <p className="text-slate-900">
                            {usage.this_month?.requests
                                ? Math.round(
                                      (usage.this_month.total_tokens || 0) / usage.this_month.requests,
                                  ).toLocaleString()
                                : 0}
                        </p>
                    </div>
                </div>
            )}

            <div className="mt-4 flex flex-wrap gap-2">
                {quickPrompts.map((prompt) => (
                    <button
                        key={prompt}
                        type="button"
                        disabled={loading}
                        onClick={() => sendMessage(prompt)}
                        className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                    >
                        {prompt}
                    </button>
                ))}
            </div>

            <textarea
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                rows={3}
                placeholder="Contoh: Bulan ini keuangan saya aman tidak?"
                className="mt-4 block w-full rounded-xl border-slate-300 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />

            <button
                type="button"
                disabled={loading || !message.trim()}
                onClick={() => sendMessage()}
                className="mt-3 inline-flex rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50"
            >
                {loading ? 'Thinking...' : 'Ask AI'}
            </button>

            {error && (
                <p className="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</p>
            )}

            {response && (
                <div className="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <div className="mb-2 flex flex-wrap gap-2">
                        {response.structured?.intent && (
                            <span className="rounded-lg bg-white px-2 py-0.5 text-xs text-slate-600">
                                {response.structured.intent}
                            </span>
                        )}
                        {response.type === 'confirmation_required' && (
                            <span className="rounded-lg bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                Confirmation required
                            </span>
                        )}
                        {response.type === 'blocked' && (
                            <span className="rounded-lg bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">
                                Blocked
                            </span>
                        )}
                    </div>
                    <p className="whitespace-pre-wrap text-sm text-slate-800">{response.message}</p>

                    {response.type === 'confirmation_required' && response.draft_id && (
                        <div className="mt-4 flex gap-2">
                            <button
                                type="button"
                                disabled={loading}
                                onClick={handleConfirm}
                                className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                            >
                                Confirm
                            </button>
                            <button
                                type="button"
                                disabled={loading}
                                onClick={handleCancel}
                                className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                            >
                                Cancel
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
