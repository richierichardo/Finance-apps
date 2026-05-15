export default function PageHeader({ title, subtitle, actions }) {
    return (
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 className="text-2xl font-semibold text-slate-950">{title}</h1>
                {subtitle && (
                    <p className="mt-1 text-sm text-slate-600">{subtitle}</p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>
            )}
        </div>
    );
}
