import axios from 'axios';
import { useState } from 'react';

export default function TelegramLinkCard() {
    const [loading, setLoading] = useState(false);
    const [linkData, setLinkData] = useState(null);
    const [error, setError] = useState(null);

    const generateCode = async () => {
        setLoading(true);
        setError(null);
        try {
            const { data } = await axios.post(route('telegram.link-token'));
            setLinkData(data);
        } catch (err) {
            setError(err.response?.data?.message || 'Gagal membuat kode.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="max-w-xl">
            <h2 className="text-lg font-medium text-gray-900">Telegram Bot</h2>
            <p className="mt-1 text-sm text-gray-600">
                Hubungkan akun Telegram untuk mengecek saldo dan mencatat transaksi via chat.
            </p>

            <button
                type="button"
                onClick={generateCode}
                disabled={loading}
                className="mt-4 inline-flex rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
            >
                {loading ? 'Generating...' : 'Generate link code'}
            </button>

            {error && <p className="mt-3 text-sm text-rose-600">{error}</p>}

            {linkData && (
                <div className="mt-4 rounded-xl border border-indigo-100 bg-indigo-50 p-4">
                    <p className="text-sm text-slate-600">Kirim ke bot Telegram:</p>
                    <p className="mt-2 font-mono text-lg font-semibold text-indigo-900">
                        /link {linkData.code}
                    </p>
                    <p className="mt-2 text-xs text-slate-500">
                        Berlaku {linkData.expires_in_minutes} menit (hingga{' '}
                        {new Date(linkData.expires_at).toLocaleString('id-ID')}).
                    </p>
                </div>
            )}
        </div>
    );
}
