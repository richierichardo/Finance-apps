import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center rounded-xl px-4 py-2 text-sm font-medium transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? 'border border-indigo-100 bg-indigo-50 text-indigo-700'
                    : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900') +
                className
            }
        >
            {children}
        </Link>
    );
}
