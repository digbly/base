import { useState, type FormEvent } from 'react';
import { ShieldCheck } from 'lucide-react';
import Button from '@/components/ui/Button';
import ErrorAlert from '@/components/ui/ErrorAlert';
import Input from '@/components/ui/Input';
import { EMAIL_PATTERN, MIN_PASSWORD_LENGTH } from '@/lib/validation';
import { useTranslation } from '@/hooks/useTranslation';
import type { AdminUser, Role } from '../../types';

export interface UserFormValues {
    name: string;
    email: string;
    roles: string[];
    is_super_admin: boolean;
    password: string;
    password_confirmation: string;
}

interface UserFormProps {
    user: AdminUser | null;
    selfId?: string | number;
    roles: Role[];
    isSubmitting: boolean;
    error: string | null;
    onSubmit: (values: UserFormValues) => void;
    onCancel: () => void;
}

export default function UserForm({ user, selfId, roles, isSubmitting, error, onSubmit, onCancel }: UserFormProps) {
    const { t } = useTranslation();
    const isEdit = Boolean(user);
    const isSelf = Boolean(user) && String(user?.id) === String(selfId);

    const [values, setValues] = useState<UserFormValues>(() => ({
        name: user?.name ?? '',
        email: user?.email ?? '',
        roles: user?.roles ?? [],
        is_super_admin: user?.is_super_admin ?? false,
        password: '',
        password_confirmation: '',
    }));
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

    const setField = <K extends keyof UserFormValues>(field: K, value: UserFormValues[K]) => {
        setValues((previous) => ({ ...previous, [field]: value }));
        setFieldErrors((previous) => ({ ...previous, [field]: '' }));
    };

    const toggleRole = (role: string) => {
        setValues((previous) => ({
            ...previous,
            roles: previous.roles.includes(role)
                ? previous.roles.filter((item) => item !== role)
                : [...previous.roles, role],
        }));
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const errors: Record<string, string> = {};

        if (!values.name.trim()) {
            errors.name = t('network.networkAdmin.userForm.errors.nameRequired', 'Full name is required.');
        }

        if (!values.email.trim()) {
            errors.email = t('network.networkAdmin.userForm.errors.emailRequired', 'Email address is required.');
        } else if (!EMAIL_PATTERN.test(values.email.trim())) {
            errors.email = t('network.networkAdmin.userForm.errors.emailInvalid', 'Please enter a valid email address.');
        }

        if (!isEdit) {
            if (!values.password) {
                errors.password = t('network.networkAdmin.userForm.errors.passwordRequired', 'Password is required.');
            } else if (values.password.length < MIN_PASSWORD_LENGTH) {
                errors.password = t('network.networkAdmin.userForm.errors.passwordMin', 'Password must be at least {{min}} characters.').replace(
                    '{{min}}',
                    String(MIN_PASSWORD_LENGTH)
                );
            }

            if (!values.password_confirmation) {
                errors.password_confirmation = t('network.networkAdmin.userForm.errors.confirmRequired', 'Please confirm the password.');
            } else if (values.password !== values.password_confirmation) {
                errors.password_confirmation = t('network.networkAdmin.userForm.errors.passwordMismatch', 'Passwords do not match.');
            }
        }

        setFieldErrors(errors);

        if (Object.keys(errors).length > 0) {
            return;
        }

        onSubmit({ ...values, name: values.name.trim(), email: values.email.trim() });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {error && <ErrorAlert message={error} />}

            <Input
                label={t('network.networkAdmin.userForm.name', 'Full name')}
                placeholder={t('network.networkAdmin.userForm.namePlaceholder', 'Alex Tran')}
                value={values.name}
                onChange={(event) => setField('name', event.target.value)}
                error={fieldErrors.name}
            />

            <Input
                label={t('network.networkAdmin.userForm.email', 'Email address')}
                type="email"
                placeholder={t('network.networkAdmin.userForm.emailPlaceholder', 'you@example.com')}
                value={values.email}
                onChange={(event) => setField('email', event.target.value)}
                error={fieldErrors.email}
            />

            <div>
                <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                    {t('network.networkAdmin.userForm.roles', 'Roles')}
                </span>

                {roles.length === 0 ? (
                    <p className="py-1 text-xs text-slate-500 dark:text-slate-400">
                        {t('network.networkAdmin.userForm.noRoles', 'No roles available yet.')}
                    </p>
                ) : (
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {roles.map((role) => (
                            <label
                                key={role.id}
                                className={`flex items-center gap-2.5 rounded-xl border px-3 py-2.5 text-sm transition-colors ${
                                    values.roles.includes(role.name)
                                        ? 'border-indigo-500/40 bg-indigo-500/5 text-slate-900 dark:text-white'
                                        : 'border-slate-200 text-slate-600 dark:border-white/[0.08] dark:text-slate-300'
                                } ${isSelf ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'}`}
                            >
                                <input
                                    type="checkbox"
                                    className="h-4 w-4 accent-indigo-600"
                                    checked={values.roles.includes(role.name)}
                                    disabled={isSelf}
                                    onChange={() => toggleRole(role.name)}
                                />
                                <span className="truncate">{role.name}</span>
                            </label>
                        ))}
                    </div>
                )}
                {isSelf && (
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {t('network.networkAdmin.userForm.selfRolesHint', 'You cannot change your own roles.')}
                    </p>
                )}
            </div>

            <label
                className={`flex items-start gap-3 rounded-xl border border-slate-200 px-3.5 py-3 dark:border-white/[0.08] ${
                    isSelf ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'
                }`}
            >
                <input
                    type="checkbox"
                    className="mt-0.5 h-4 w-4 accent-indigo-600"
                    checked={values.is_super_admin}
                    disabled={isSelf}
                    onChange={(event) => setField('is_super_admin', event.target.checked)}
                />
                <span>
                    <span className="flex items-center gap-1.5 text-sm font-medium text-slate-900 dark:text-white">
                        <ShieldCheck className="h-4 w-4 text-indigo-500" />
                        {t('network.networkAdmin.userForm.superAdmin', 'Super admin')}
                    </span>
                    <span className="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                        {t('network.networkAdmin.userForm.superAdminHint', 'Grants every permission, ignoring assigned roles.')}
                    </span>
                </span>
            </label>

            {!isEdit && (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Input
                        label={t('network.networkAdmin.userForm.password', 'Password')}
                        type="password"
                        placeholder={t('network.networkAdmin.userForm.passwordPlaceholder', 'Min. 8 characters')}
                        value={values.password}
                        onChange={(event) => setField('password', event.target.value)}
                        error={fieldErrors.password}
                    />
                    <Input
                        label={t('network.networkAdmin.userForm.confirmPassword', 'Confirm password')}
                        type="password"
                        placeholder={t('network.networkAdmin.userForm.confirmPasswordPlaceholder', 'Repeat password')}
                        value={values.password_confirmation}
                        onChange={(event) => setField('password_confirmation', event.target.value)}
                        error={fieldErrors.password_confirmation}
                    />
                </div>
            )}

            <div className="flex justify-end gap-3 pt-2">
                <Button type="button" variant="outline" onClick={onCancel} disabled={isSubmitting}>
                    {t('network.networkAdmin.userForm.cancel', 'Cancel')}
                </Button>
                <Button type="submit" isLoading={isSubmitting}>
                    {isEdit
                        ? t('network.networkAdmin.userForm.save', 'Save changes')
                        : t('network.networkAdmin.userForm.create', 'Create user')}
                </Button>
            </div>
        </form>
    );
}
