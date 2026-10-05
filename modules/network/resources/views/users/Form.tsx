import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Button from '@/components/ui/Button';
import { route } from '@/lib/route';
import { useTranslation } from '@/hooks/useTranslation';
import NetworkLayout from '../layouts/NetworkLayout';
import type { AdminUser, Role } from '../types';
import UserForm, { type UserFormValues } from './components/UserForm';

interface UserFormPageProps {
    title: string;
    user: AdminUser | null;
    roles: Role[];
    selfId: string;
}

const firstError = (errors: Record<string, string>): string => Object.values(errors)[0] ?? '';

export default function UserFormPage({ title, user, roles, selfId }: UserFormPageProps) {
    const { t } = useTranslation();

    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const backUrl = route('admin.network.users.index');

    const submit = (values: UserFormValues) => {
        setError(null);

        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (errors: Record<string, string>) =>
                setError(firstError(errors) || t('network.networkAdmin.errors.saveFailed', 'Failed to save. Please try again.')),
        };

        if (user) {
            const payload = {
                name: values.name,
                email: values.email,
                roles: values.roles,
                is_super_admin: values.is_super_admin,
            };

            router.put(
                route('admin.network.users.update', { user: user.id }),
                payload as unknown as Parameters<typeof router.put>[1],
                options
            );
        } else {
            const payload = {
                name: values.name,
                email: values.email,
                roles: values.roles,
                is_super_admin: values.is_super_admin,
                password: values.password,
                password_confirmation: values.password_confirmation,
            };

            router.post(
                route('admin.network.users.store'),
                payload as unknown as Parameters<typeof router.post>[1],
                options
            );
        }
    };

    return (
        <NetworkLayout title={title}>
            <div className="space-y-6">
                <div className="flex flex-col gap-3">
                    <Link href={backUrl} className="w-fit">
                        <Button variant="ghost" size="sm" className="-ml-2" leftIcon={<ArrowLeft className="h-4 w-4" />}>
                            {t('network.networkAdmin.userForm.back', 'Back to users')}
                        </Button>
                    </Link>

                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {user
                                ? t('network.networkAdmin.userForm.editSubtitle', 'Update account details and role.')
                                : t('network.networkAdmin.userForm.createSubtitle', 'Create a new account on the network.')}
                        </p>
                    </div>
                </div>

                <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <UserForm
                        key={user?.id ?? 'new'}
                        user={user}
                        selfId={selfId}
                        roles={roles}
                        isSubmitting={processing}
                        error={error}
                        onSubmit={submit}
                        onCancel={() => router.visit(backUrl)}
                    />
                </div>
            </div>
        </NetworkLayout>
    );
}
