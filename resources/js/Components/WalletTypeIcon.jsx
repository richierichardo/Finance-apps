const typeStyles = {
    bank: {
        container: 'bg-blue-50 text-blue-600',
        icon: (
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M2.25 21h19.5M4.5 21V9.75m15 11.25V9.75M6 21V9.75m12 11.25V9.75M9 6.75V4.5a2.25 2.25 0 012.25-2.25h1.5A2.25 2.25 0 0115 4.5v2.25m-6 0h6m-6 0H6.75m8.25 0H18"
            />
        ),
    },
    ewallet: {
        container: 'bg-violet-50 text-violet-600',
        icon: (
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"
            />
        ),
    },
    cash: {
        container: 'bg-emerald-50 text-emerald-600',
        icon: (
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M2.25 18.75a60.07 60.07 0 0115.797 0M2.25 15.75a48.08 48.08 0 014.5 0m-4.5 3a60.07 60.07 0 0115.797 0M12 7.5V4.875m0 0A2.625 2.625 0 109.375 7.5H12Zm0 0V9.75m0-2.25h2.25M12 7.5h2.25m-2.25 0H9.375"
            />
        ),
    },
    default: {
        container: 'bg-indigo-50 text-indigo-600',
        icon: (
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3"
            />
        ),
    },
};

export default function WalletTypeIcon({ type, className = '' }) {
    const key = String(type ?? '').toLowerCase();
    const style = typeStyles[key] ?? typeStyles.default;

    return (
        <div
            className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${style.container} ${className}`}
        >
            <svg
                className="h-6 w-6"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                strokeWidth={1.5}
                stroke="currentColor"
                aria-hidden="true"
            >
                {style.icon}
            </svg>
        </div>
    );
}
