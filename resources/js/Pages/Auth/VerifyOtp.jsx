import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import { Head } from '@inertiajs/react';

export default function VerifyOtp({ email: initialEmail }) {
    const [email, setEmail] = useState(initialEmail);
    const [otp, setOtp] = useState('');
    const [resending, setResending] = useState(false);
    const [resendCountdown, setResendCountdown] = useState(0);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: initialEmail,
        otp_code: '',
    });

    const handleOtpChange = (e) => {
        const value = e.target.value.replace(/\D/g, '').slice(0, 6);
        setOtp(value);
        setData('otp_code', value);
    };

    useEffect(() => {
        if (otp.length === 6) {
            handleVerify();
        }
    }, [otp]);

    useEffect(() => {
        if (resendCountdown > 0) {
            const timer = setTimeout(
                () => setResendCountdown(resendCountdown - 1),
                1000,
            );
            return () => clearTimeout(timer);
        }
        setResending(false);
    }, [resendCountdown]);

    const handleVerify = () => {
        post('/verify-otp', {
            onSuccess: (response) => {
                if (response.props.message) {
                    window.location.href =
                        response.props.redirect || '/dashboard';
                }
            },
            onError: (errors) => {
                if (errors.otp_code) {
                    fetch('/increment-otp-attempt', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': document.querySelector(
                                'meta[name="csrf-token"]',
                            )?.content || '',
                        },
                        body: JSON.stringify({ email }),
                    });
                    setOtp('');
                }
            },
        });
    };

    const handleResend = () => {
        setResending(true);
        setResendCountdown(120);
        setOtp('');
        reset('otp_code');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')
            ?.content || '';

        fetch('/send-otp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({ email }),
        })
            .then((response) => {
                if (response.status === 429) {
                    const retryAfter = response.headers.get('Retry-After');
                    const seconds = retryAfter ? parseInt(retryAfter, 10) : 60;
                    const minutes = Math.ceil(seconds / 60);
                    throw new Error(
                        `Terlalu banyak percobaan. Coba lagi dalam ${minutes} menit.`,
                    );
                }
                if (!response.ok) {
                    throw new Error('Gagal mengirim ulang OTP');
                }
                return response.json();
            })
            .then((data) => {
                if (data.email) {
                    alert('OTP telah dikirim ulang ke email Anda');
                }
            })
            .catch((err) => {
                alert(err.message || 'Gagal mengirim ulang OTP');
                setResending(false);
                setResendCountdown(0);
            });
    };

    return (
        <GuestLayout>
            <Head title="Verifikasi OTP - Finance Tracker" />

            <div className="mb-6">
                <h2 className="text-xl text-center font-bold text-slate-900 dark:text-white">
                    Verifikasi Email
                </h2>
                <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Masukkan kode 6 digit yang dikirim ke <strong className="text-slate-800 dark:text-slate-200">{email}</strong>
                </p>
            </div>

            <form className="space-y-4">
                <div>
                    <InputLabel className="text-center" htmlFor="otp_code" value="Kode OTP" />
                    <TextInput
                        id="otp_code"
                        type="text"
                        value={otp}
                        onChange={handleOtpChange}
                        placeholder="000000"
                        className="mt-1 text-center w-full text-2xl font-mono tracking-widest"
                        disabled={processing}
                        autoFocus
                        maxLength={6}
                    />
                    <InputError message={errors.otp_code} className="mt-2" />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <PrimaryButton
                    disabled={processing || otp.length < 6}
                    className="w-full"
                >
                    {processing ? 'Memverifikasi...' : 'Verifikasi Email'}
                </PrimaryButton>

                <div className="text-center">
                    <p className="text-sm text-slate-600 dark:text-slate-400">
                        Tidak menerima kode?
                    </p>
                    <button
                        type="button"
                        onClick={handleResend}
                        disabled={resending || resendCountdown > 0}
                        className="mt-2 text-sm font-medium text-emerald-600 hover:text-emerald-700 disabled:cursor-not-allowed disabled:text-slate-400 dark:text-emerald-500 dark:hover:text-emerald-400 dark:disabled:text-slate-500"
                    >
                        {resendCountdown > 0
                            ? `Kirim ulang dalam ${resendCountdown}s`
                            : 'Kirim ulang OTP'}
                    </button>
                </div>
            </form>
        </GuestLayout>
    );
}
