import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Lupa Password - Flowlet" />

            <div className="mb-6">
                <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                    Lupa Password?
                </h2>
                <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Tidak masalah. Masukkan email Anda dan kami akan mengirim link untuk mengatur ulang password.
                </p>
            </div>

            {status && (
                <div className="mb-4 rounded-lg bg-emerald-50 p-3 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="nama@email.com"
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Link
                        href={route('login')}
                        className="text-sm text-slate-600 underline hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-500"
                    >
                        ← Kembali ke login
                    </Link>
                    <PrimaryButton disabled={processing}>
                        Kirim Link Reset Password
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
