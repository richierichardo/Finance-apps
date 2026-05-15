import WalletTypeIcon from '@/Components/WalletTypeIcon';
import { formatRupiah, walletTypeLabel } from '@/utils/format';

export default function WalletPreviewCard({ name, type, balance, label = 'Preview' }) {
    return (
        <div className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
            <p className="text-xs font-medium uppercase tracking-wide text-slate-400">{label}</p>
            <div className="mt-4 flex items-start gap-3">
                <WalletTypeIcon type={type} />
                <div className="min-w-0 flex-1">
                    <p className="truncate font-semibold text-slate-950">
                        {name || 'Wallet name'}
                    </p>
                    <span className="mt-1 inline-flex rounded-lg border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-600">
                        {walletTypeLabel(type)}
                    </span>
                    <p className="mt-3 text-xl font-semibold text-slate-950">
                        {formatRupiah(balance ?? 0)}
                    </p>
                </div>
            </div>
        </div>
    );
}
