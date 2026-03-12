export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center justify-center rounded-lg border border-transparent bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-emerald-600/25 transition duration-150 ease-in-out hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 active:bg-emerald-800 dark:focus:ring-offset-slate-800 ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
