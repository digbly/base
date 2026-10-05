import { useState, type FormEvent } from 'react';
import Button from '@/components/ui/Button';
import ErrorAlert from '@/components/ui/ErrorAlert';
import Input from '@/components/ui/Input';
import Modal from '@/components/ui/Modal';
import { MIN_PASSWORD_LENGTH } from '@/lib/validation';
import { useTranslation } from '@/hooks/useTranslation';
import type { AdminUser } from '../../types';

interface ResetPasswordModalProps {
    user: AdminUser;
    isSubmitting: boolean;
    error: string | null;
    onSubmit: (values: { password: string; password_confirmation: string }) => void;
    onClose: () => void;
}

export default function ResetPasswordModal({
    user,
    isSubmitting,
    error,
    onSubmit,
    onClose,
}: ResetPasswordModalProps) {
    const { t } = useTranslation();
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const errors: Record<string, string> = {};

        if (!password) {
            errors.password = t('network.networkAdmin.userForm.errors.passwordRequired', 'Password is required.');
        } else if (password.length < MIN_PASSWORD_LENGTH) {
            errors.password = t('network.networkAdmin.userForm.errors.passwordMin', 'Password must be at least {{min}} characters.').replace(
                '{{min}}',
                String(MIN_PASSWORD_LENGTH)
            );
        }

        if (!confirmation) {
            errors.password_confirmation = t('network.networkAdmin.userForm.errors.confirmRequired', 'Please confirm the password.');
        } else if (password !== confirmation) {
            errors.password_confirmation = t('network.networkAdmin.userForm.errors.passwordMismatch', 'Passwords do not match.');
        }

        setFieldErrors(errors);

        if (Object.keys(errors).length > 0) {
            return;
        }

        onSubmit({ password, password_confirmation: confirmation });
    };

    return (
        <Modal
            open
            title={t('network.networkAdmin.resetPassword.title', 'Reset password')}
            onClose={onClose}
        >
            <p className="mb-4 text-sm text-slate-600 dark:text-slate-300">
                {t('network.networkAdmin.resetPassword.subtitle', 'Set a new password for {{name}}.').replace(
                    '{{name}}',
                    user.name
                )}
            </p>

            <form onSubmit={handleSubmit} className="space-y-4">
                {error && <ErrorAlert message={error} />}

                <Input
                    label={t('network.networkAdmin.userForm.password', 'Password')}
                    type="password"
                    value={password}
                    onChange={(event) => {
                        setPassword(event.target.value);
                        setFieldErrors((previous) => ({ ...previous, password: '' }));
                    }}
                    error={fieldErrors.password}
                />

                <Input
                    label={t('network.networkAdmin.userForm.confirmPassword', 'Confirm password')}
                    type="password"
                    value={confirmation}
                    onChange={(event) => {
                        setConfirmation(event.target.value);
                        setFieldErrors((previous) => ({ ...previous, password_confirmation: '' }));
                    }}
                    error={fieldErrors.password_confirmation}
                />

                <div className="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="outline" onClick={onClose} disabled={isSubmitting}>
                        {t('network.networkAdmin.userForm.cancel', 'Cancel')}
                    </Button>
                    <Button type="submit" isLoading={isSubmitting}>
                        {t('network.networkAdmin.resetPassword.submit', 'Reset password')}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
