export default function EmptyState({
    icon,
    title,
    subtitle,
    primaryAction,
    secondaryAction,
}) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
            {icon && (
                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                    {icon}
                </div>
            )}
            <h3 className="mt-4 text-lg font-semibold text-slate-950">{title}</h3>
            {subtitle && <p className="mt-2 max-w-md text-sm text-slate-500">{subtitle}</p>}
            {(primaryAction || secondaryAction) && (
                <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
                    {primaryAction}
                    {secondaryAction}
                </div>
            )}
        </div>
    );
}
