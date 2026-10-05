import { useState, type FormEvent } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { useForm } from 'react-hook-form';
import { KeyRound, Pencil, Plus, RotateCcw, Send, Trash2 } from 'lucide-react';
import AdminLayout from '@modules/admin/resources/views/layouts/AdminLayout';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import Modal from '@/components/ui/Modal';
import { submitForm } from '@/lib/inertia-form';
import { route } from '@/lib/route';
import { MIN_PASSWORD_LENGTH } from '@/lib/validation';
import { useTranslation } from '@/hooks/useTranslation';
import type { SharedProps } from '@/types';

interface UserRow {
    id: string;
    name: string;
    email: string;
    roles: string[];
    is_super_admin: boolean;
    email_verified_at: string | null;
    deleted_at: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface UsersProps {
    title: string;
    users: {
        data: UserRow[];
        links: PaginationLink[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: { search: string | null; role: string | null; trashed: string | null };
    roles: string[];
    selfId: string;
    canManageSuperAdmin: boolean;
}

interface ResetPasswordForm {
    password: string;
    password_confirmation: string;
}

/** Decode the two HTML entities Laravel ships in pagination labels. */
const pageLabel = (label: string): string => label.replace(/&laquo;/g, '«').replace(/&raquo;/g, '»');

export default function Users({ title, users, filters, roles }: UsersProps) {
    const { t } = useTranslation();

    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<UserRow | null>(null);
    const [resetTarget, setResetTarget] = useState<UserRow | null>(null);

    const indexUrl = route('admin.users.index');

    const applyFilters = (overrides: Record<string, string | null> = {}) => {
        const params: Record<string, string> = {};
        const merged = { search, role: filters.role, trashed: filters.trashed, ...overrides };

        Object.entries(merged).forEach(([key, value]) => {
            if (value) {
                params[key] = value;
            }
        });

        router.get(indexUrl, params, { preserveState: true, replace: true });
    };

    const onSearch = (event: FormEvent) => {
        event.preventDefault();
        applyFilters({ search });
    };

    const resetForm = useForm<ResetPasswordForm>({
        defaultValues: { password: '', password_confirmation: '' },
    });

    const submitReset = resetForm.handleSubmit((data) => {
        if (!resetTarget) {
            return;
        }

        return submitForm(
            route('admin.users.password', { user: resetTarget.id }),
            data,
            {
                method: 'put',
                setError: resetForm.setError,
                onSuccess: () => {
                    resetForm.reset();
                    setResetTarget(null);
                },
            }
        );
    });

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(route('admin.users.destroy', { user: deleteTarget.id }), {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    return (
        <AdminLayout title={title}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-bold">{title}</h1>
                <Link href={route('admin.users.create')}>
                    <Button leftIcon={<Plus className="h-4 w-4" />}>{t('admin.users.addUser', 'Add user')}</Button>
                </Link>
            </div>

            <form onSubmit={onSearch} className="mb-4 flex flex-wrap gap-3">
                <div className="flex-1 min-w-[200px]">
                    <Input
                        placeholder={t('admin.users.searchPlaceholder', 'Search users...')}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </div>

                <select
                    value={filters.role ?? ''}
                    onChange={(event) => applyFilters({ role: event.target.value })}
                    className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                >
                    <option value="">{t('admin.users.filters.allRoles', 'All roles')}</option>
                    {roles.map((role) => (
                        <option key={role} value={role}>
                            {role}
                        </option>
                    ))}
                </select>

                <select
                    value={filters.trashed ?? ''}
                    onChange={(event) => applyFilters({ trashed: event.target.value })}
                    className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                >
                    <option value="">{t('admin.users.filters.active', 'Active')}</option>
                    <option value="with">{t('admin.users.filters.withTrashed', 'With trashed')}</option>
                    <option value="only">{t('admin.users.filters.onlyTrashed', 'Trashed only')}</option>
                </select>

                <Button type="submit" variant="secondary">
                    {t('admin.users.filters.search', 'Search')}
                </Button>
            </form>

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-slate-200 text-xs uppercase text-slate-500 dark:border-slate-800">
                        <tr>
                            <th className="px-4 py-3">{t('admin.users.table.name', 'Name')}</th>
                            <th className="px-4 py-3">{t('admin.users.table.roles', 'Roles')}</th>
                            <th className="px-4 py-3">{t('admin.users.table.status', 'Status')}</th>
                            <th className="px-4 py-3 text-right">{t('admin.users.table.actions', 'Actions')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.data.length === 0 && (
                            <tr>
                                <td colSpan={4} className="px-4 py-8 text-center text-slate-500">
                                    {t('admin.users.empty', 'No users found.')}
                                </td>
                            </tr>
                        )}

                        {users.data.map((user) => (
                            <tr
                                key={user.id}
                                className="border-b border-slate-100 last:border-0 dark:border-slate-800/60"
                            >
                                <td className="px-4 py-3">
                                    <div className="font-medium">{user.name}</div>
                                    <div className="text-xs text-slate-500">{user.email}</div>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex flex-wrap gap-1">
                                        {user.is_super_admin && (
                                            <span className="rounded bg-indigo-500/10 px-2 py-0.5 text-xs text-indigo-600 dark:text-indigo-400">
                                                {t('admin.users.table.superAdmin', 'Super admin')}
                                            </span>
                                        )}
                                        {user.roles.map((role) => (
                                            <span
                                                key={role}
                                                className="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                            >
                                                {role}
                                            </span>
                                        ))}
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    {user.deleted_at ? (
                                        <span className="text-xs text-rose-500">
                                            {t('admin.users.status.deleted', 'Deleted')}
                                        </span>
                                    ) : user.email_verified_at ? (
                                        <span className="text-xs text-emerald-600">
                                            {t('admin.users.status.verified', 'Verified')}
                                        </span>
                                    ) : (
                                        <span className="text-xs text-amber-600">
                                            {t('admin.users.status.unverified', 'Unverified')}
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-1">
                                        {user.deleted_at ? (
                                            <button
                                                type="button"
                                                title={t('admin.users.actions.restore', 'Restore')}
                                                onClick={() =>
                                                    router.post(
                                                        route('admin.users.restore', { user: user.id }),
                                                        {},
                                                        { preserveScroll: true }
                                                    )
                                                }
                                                className="rounded p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                                            >
                                                <RotateCcw className="h-4 w-4" />
                                            </button>
                                        ) : (
                                            <>
                                                <Link
                                                    href={route('admin.users.edit', { user: user.id })}
                                                    title={t('admin.users.actions.edit', 'Edit')}
                                                    className="rounded p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                                <button
                                                    type="button"
                                                    title={t('admin.users.actions.resetPassword', 'Reset password')}
                                                    onClick={() => setResetTarget(user)}
                                                    className="rounded p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                                                >
                                                    <KeyRound className="h-4 w-4" />
                                                </button>
                                                {!user.email_verified_at && (
                                                    <button
                                                        type="button"
                                                        title={t('admin.users.actions.resendVerification', 'Resend verification')}
                                                        onClick={() =>
                                                            router.post(
                                                                route('admin.users.resend-verification', {
                                                                    user: user.id,
                                                                }),
                                                                {},
                                                                { preserveScroll: true }
                                                            )
                                                        }
                                                        className="rounded p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                                                    >
                                                        <Send className="h-4 w-4" />
                                                    </button>
                                                )}
                                                <button
                                                    type="button"
                                                    title={t('admin.users.actions.delete', 'Delete')}
                                                    onClick={() => setDeleteTarget(user)}
                                                    className="rounded p-2 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {users.meta.last_page > 1 && (
                <div className="mt-4 flex justify-center gap-1">
                    {users.links.map((link, index) =>
                        link.url ? (
                            <button
                                key={index}
                                type="button"
                                onClick={() => router.get(link.url!, {}, { preserveState: true })}
                                className={`rounded-md px-3 py-1.5 text-xs ${
                                    link.active
                                        ? 'bg-indigo-600 text-white'
                                        : 'border border-slate-200 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300'
                                }`}
                            >
                                {pageLabel(link.label)}
                            </button>
                        ) : (
                            <span key={index} className="rounded-md px-3 py-1.5 text-xs text-slate-400">
                                {pageLabel(link.label)}
                            </span>
                        )
                    )}
                </div>
            )}

            <Modal
                open={deleteTarget !== null}
                title={t('admin.users.deleteDialog.title', 'Delete user')}
                onClose={() => setDeleteTarget(null)}
            >
                <p className="mb-5 text-sm text-slate-600 dark:text-slate-300">
                    {t('admin.users.deleteDialog.message', 'Are you sure you want to delete this user?')}
                </p>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={() => setDeleteTarget(null)}>
                        {t('admin.users.form.cancel', 'Cancel')}
                    </Button>
                    <Button variant="danger" onClick={confirmDelete}>
                        {t('admin.users.actions.delete', 'Delete')}
                    </Button>
                </div>
            </Modal>

            <Modal
                open={resetTarget !== null}
                title={t('admin.users.resetPassword.title', 'Reset password')}
                onClose={() => setResetTarget(null)}
            >
                <form onSubmit={submitReset} className="space-y-4">
                    <Input
                        label={t('admin.users.form.password', 'Password')}
                        type="password"
                        autoComplete="new-password"
                        error={resetForm.formState.errors.password?.message}
                        {...resetForm.register('password', {
                            required: 'Password is required',
                            minLength: { value: MIN_PASSWORD_LENGTH, message: `Min ${MIN_PASSWORD_LENGTH} characters` },
                        })}
                    />
                    <Input
                        label={t('admin.users.form.confirmPassword', 'Confirm password')}
                        type="password"
                        autoComplete="new-password"
                        error={resetForm.formState.errors.password_confirmation?.message}
                        {...resetForm.register('password_confirmation', {
                            validate: (value) =>
                                value === resetForm.getValues('password') || 'Passwords do not match',
                        })}
                    />
                    <div className="flex justify-end gap-2">
                        <Button variant="secondary" onClick={() => setResetTarget(null)}>
                            {t('admin.users.form.cancel', 'Cancel')}
                        </Button>
                        <Button type="submit" isLoading={resetForm.formState.isSubmitting}>
                            {t('admin.users.resetPassword.submit', 'Reset password')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AdminLayout>
    );
}
