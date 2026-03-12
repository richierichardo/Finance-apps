import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Verifikasi Email - Finance Tracker" />

            <div className="mb-6">
                <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                    Verifikasi Email
                </h2>
                <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Terima kasih telah mendaftar! Sebelum memulai, verifikasi alamat email Anda dengan mengklik link yang kami kirim. Tidak menerima email? Kami akan dengan senang hati mengirim ulang.
                </p>
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 rounded-lg bg-emerald-50 p-3 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                    Link verifikasi baru telah dikirim ke email Anda.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <PrimaryButton disabled={processing}>
                        Kirim Ulang Email Verifikasi
                    </PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-sm text-slate-600 underline hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-500"
                    >
                        Log Out
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
