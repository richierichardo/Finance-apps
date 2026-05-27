import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { router } from '@inertiajs/react';
import { useState } from 'react';

export default function LogoutConfirmModal({ show, onClose }) {
    const [processing, setProcessing] = useState(false);

    const confirmLogout = () => {
        setProcessing(true);
        router.post(route('logout'), {}, {
            onFinish: () => {
                setProcessing(false);
                onClose();
            },
        });
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="sm">
            <div className="p-6">
                <h2 className="text-lg font-semibold text-gray-900">Log out?</h2>
                <p className="mt-2 text-sm text-gray-600">
                    You will need to sign in again to access your account.
                </p>

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={onClose} disabled={processing}>
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton type="button" onClick={confirmLogout} disabled={processing}>
                        {processing ? 'Logging out…' : 'Log out'}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    );
}
