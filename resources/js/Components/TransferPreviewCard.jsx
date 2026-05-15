import WalletTypeIcon from '@/Components/WalletTypeIcon';
import { formatRupiah } from '@/utils/format';
import { parseCurrencyToNumber } from '@/utils/currency';

function WalletBalanceBlock({ label, wallet, afterBalance }) {
    if (!wallet) {
        return (
            <div className="rounded-xl border border-slate-100 bg-white p-4">
                <p className="text-xs font-medium uppercase text-slate-400">{label}</p>
                <p className="mt-1 text-sm text-slate-500">Select a wallet</p>
            </div>
        );
    }

    return (
        <div className="rounded-xl border border-slate-100 bg-white p-4">
            <div className="flex items-start gap-3">
                <WalletTypeIcon type={wallet.type} className="h-10 w-10" />
                <div className="min-w-0">
                    <p className="text-xs font-medium uppercase text-slate-400">{label}</p>
                    <p className="truncate font-semibold text-slate-900">{wallet.name}</p>
                </div>
            </div>
            <div className="mt-3 space-y-1 text-sm">
                <div className="flex justify-between gap-2">
                    <span className="text-slate-500">Current</span>
                    <span className="font-medium text-slate-900">{formatRupiah(wallet.balance)}</span>
                </div>
                <div className="flex justify-between gap-2">
                    <span className="text-slate-500">After transfer</span>
                    <span className="font-semibold text-indigo-700">{formatRupiah(afterBalance)}</span>
                </div>
            </div>
        </div>
    );
}

export default function TransferPreviewCard({
    wallets = [],
    fromWalletId,
    toWalletId,
    amount,
}) {
    const fromWallet = wallets.find((w) => String(w.id) === String(fromWalletId));
    const toWallet = wallets.find((w) => String(w.id) === String(toWalletId));
    const amountNum = parseCurrencyToNumber(amount);

    const sameWallet =
        fromWalletId && toWalletId && String(fromWalletId) === String(toWalletId);

    const fromAfter =
        fromWallet && amountNum > 0
            ? Number(fromWallet.balance ?? 0) - amountNum
            : Number(fromWallet?.balance ?? 0);

    const toAfter =
        toWallet && amountNum > 0
            ? Number(toWallet.balance ?? 0) + amountNum
            : Number(toWallet?.balance ?? 0);

    const overBalance =
        fromWallet && amountNum > 0 && amountNum > Number(fromWallet.balance ?? 0);

    return (
        <div className="rounded-2xl border border-slate-200/80 bg-slate-50 p-5 shadow-sm lg:sticky lg:top-24">
            <h3 className="text-sm font-semibold text-slate-950">Transfer Preview</h3>
            <p className="mt-1 text-xs text-slate-500">
                Preview is based on the selected wallets and amount before saving.
            </p>

            <div className="mt-4 space-y-3">
                <WalletBalanceBlock
                    label="From wallet"
                    wallet={fromWallet}
                    afterBalance={fromAfter}
                />

                <div className="flex justify-center">
                    <svg
                        className="h-5 w-5 text-slate-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        strokeWidth={1.5}
                        stroke="currentColor"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3"
                        />
                    </svg>
                </div>

                <WalletBalanceBlock
                    label="To wallet"
                    wallet={toWallet}
                    afterBalance={toAfter}
                />
            </div>

            <div className="mt-4 rounded-xl border border-slate-100 bg-white px-4 py-3">
                <p className="text-xs font-medium uppercase text-slate-400">Amount</p>
                <p className="mt-1 text-lg font-semibold text-slate-950">
                    {amountNum > 0 ? formatRupiah(amountNum) : '—'}
                </p>
            </div>

            {sameWallet && (
                <div className="mt-4 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-sm text-amber-700">
                    Source and destination wallet should be different.
                </div>
            )}

            {overBalance && (
                <div className="mt-4 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-sm text-amber-700">
                    Amount is higher than the selected wallet balance.
                </div>
            )}
        </div>
    );
}
