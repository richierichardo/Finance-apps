import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in - Flowlet" />

            <div className="mb-6">
                <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                    Log in
                </h2>
                <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Masuk ke akun Flowlet Anda
                </p>
            </div>

            {status && (
                <div className="mb-4 rounded-lg bg-emerald-50 p-3 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="login" value="Email atau Username" />

                    <TextInput
                        id="login"
                        type="text"
                        name="login"
                        value={data.login}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('login', e.target.value)}
                        placeholder="email atau username"
                    />

                    <InputError message={errors.login} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Password" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="flex items-center">
                    <Checkbox
                        name="remember"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                    />
                    <label className="ml-2 text-sm text-slate-600 dark:text-slate-400">
                        Ingat saya
                    </label>
                </div>

                <div className="flex flex-col gap-4 pt-2 sm:flex-row sm:items-center sm:justify-between">
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-sm text-slate-600 underline hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-500"
                        >
                            Lupa password?
                        </Link>
                    )}
                    <PrimaryButton disabled={processing} className="sm:ml-auto">
                        Log in
                    </PrimaryButton>
                </div>
            </form>

            <p className="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">
                Belum punya akun?{' '}
                <Link
                    href={route('register')}
                    className="font-medium text-emerald-600 underline hover:text-emerald-700 dark:text-emerald-500 dark:hover:text-emerald-400"
                >
                    Daftar sekarang
                </Link>
            </p>
        </GuestLayout>
    );
}
