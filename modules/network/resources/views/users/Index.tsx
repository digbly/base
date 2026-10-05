import { useState, type FormEvent } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { KeyRound, Mail, Pencil, RotateCcw, Search, ShieldCheck, Trash2, UserPlus, Users as UsersIcon } from 'lucide-react';
import Badge, { roleBadgeVariant } from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import ErrorAlert from '@/components/ui/ErrorAlert';
import Input from '@/components/ui/Input';
import { route } from '@/lib/route';
import { useTranslation } from '@/hooks/useTranslation';
import type { SharedProps } from '@/types';
import ConfirmDialog from '../components/ConfirmDialog';
import Pagination from '../components/Pagination';
import NetworkLayout from '../layouts/NetworkLayout';
import type { AdminUser, Paginated, Role } from '../types';
import ResetPasswordModal from './components/ResetPasswordModal';

interface UsersProps {
    title: string;
    users: Paginated<AdminUser>;
    filters: { search: string | null; role: string | null; trashed: string | null };
    roles: Role[];
    selfId: string;
}

const firstError = (errors: Record<string, string>): string => Object.values(errors)[0] ?? '';

export default function Users({ title, users, filters, roles, selfId }: UsersProps) {
    const { t } = useTranslation();
    const { locale } = usePage<SharedProps>().props;

    const [search, setSearch] = useState(filters.search ?? '');
    const [role, setRole] = useState(filters.role ?? '');
    const [trashed, setTrashed] = useState(filters.trashed ?? '');
    const [deleteTarget, setDeleteTarget] = useState<AdminUser | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [resetTarget, setResetTarget] = useState<AdminUser | null>(null);
    const [resetting, setResetting] = useState(false);
    const [resetError, setResetError] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const indexUrl = route('admin.network.users.index');

    const applyFilters = (overrides: Record<string, string | null> = {}) => {
        const params: Record<string, string> = {};
        const merged = { search, role, trashed, ...overrides };

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

    const formatDate = (value?: string | null): string =>
        value
            ? new Date(value).toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' })
            : '—';

    const restore = (user: AdminUser) => {
        setError(null);
        router.post(route('admin.network.users.restore', { user: user.id }), {}, { preserveScroll: true });
    };

    const resend = (user: AdminUser) => {
        setError(null);
        router.post(route('admin.network.users.resend-verification', { user: user.id }), {}, { preserveScroll: true });
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setError(null);
        setDeleting(true);

        router.delete(route('admin.network.users.destroy', { user: deleteTarget.id }), {
            preserveScroll: true,
            onError: () => setError(t('network.networkAdmin.errors.deleteFailed', 'Failed to delete. Please try again.')),
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    const submitReset = (values: { password: string; password_confirmation: string }) => {
        if (!resetTarget) {
            return;
        }

        setResetError(null);
        setResetting(true);

        router.put(route('admin.network.users.password', { user: resetTarget.id }), values, {
            preserveScroll: true,
            onSuccess: () => setResetTarget(null),
            onError: (errors: Record<string, string>) =>
                setResetError(firstError(errors) || t('network.networkAdmin.errors.resetFailed', 'Failed to reset the password.')),
            onFinish: () => setResetting(false),
        });
    };

    return (
        <NetworkLayout title={title}>
            <div className="space-y-5">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                    <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        {t('network.networkAdmin.users.subtitle', 'Manage every account on the network.')}
                    </p>
                </div>

                {error && <ErrorAlert message={error} />}

                <div className="flex justify-end">
                    <Link href={route('admin.network.users.create')}>
                        <Button leftIcon={<UserPlus className="h-4 w-4" />}>
                            {t('network.networkAdmin.users.add', 'Add user')}
                        </Button>
                    </Link>
                </div>

                <form onSubmit={onSearch} className="flex flex-wrap gap-3">
                    <div className="min-w-[200px] flex-1">
                        <Input
                            placeholder={t('network.networkAdmin.users.searchPlaceholder', 'Search by name or email')}
                            leftIcon={<Search className="h-4 w-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    <select
                        value={role}
                        onChange={(event) => {
                            setRole(event.target.value);
                            applyFilters({ role: event.target.value });
                        }}
                        className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <option value="">{t('network.networkAdmin.users.allRoles', 'All roles')}</option>
                        {roles.map((option) => (
                            <option key={option.id} value={option.name}>
                                {option.name}
                            </option>
                        ))}
                    </select>

                    <select
                        value={trashed}
                        onChange={(event) => {
                            setTrashed(event.target.value);
                            applyFilters({ trashed: event.target.value });
                        }}
                        className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <option value="">{t('network.networkAdmin.users.activeUsers', 'Active users')}</option>
                        <option value="only">{t('network.networkAdmin.users.deletedOnly', 'Deleted only')}</option>
                        <option value="with">{t('network.networkAdmin.users.allUsers', 'All users')}</option>
                    </select>

                    <Button type="submit" variant="secondary">
                        {t('network.networkAdmin.users.search', 'Search')}
                    </Button>
                </form>

                <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-left text-sm">
                            <thead className="border-b border-slate-200 text-xs uppercase text-slate-500 dark:border-slate-800">
                                <tr>
                                    <th className="px-6 py-3 font-semibold">{t('network.networkAdmin.users.table.user', 'User')}</th>
                                    <th className="px-6 py-3 font-semibold">{t('network.networkAdmin.users.table.role', 'Role')}</th>
                                    <th className="px-6 py-3 font-semibold">{t('network.networkAdmin.users.table.status', 'Status')}</th>
                                    <th className="px-6 py-3 font-semibold">{t('network.networkAdmin.users.table.joined', 'Joined')}</th>
                                    <th className="px-6 py-3 text-right font-semibold">{t('network.networkAdmin.users.table.actions', 'Actions')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {users.data.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-12 text-center">
                                            <div className="flex flex-col items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                                <UsersIcon className="h-6 w-6 text-slate-400" />
                                                <span>{t('network.networkAdmin.users.empty', 'No users match your filters.')}</span>
                                            </div>
                                        </td>
                                    </tr>
                                )}

                                {users.data.map((user) => {
                                    const userRoles = user.roles ?? [];
                                    const isSelf = String(user.id) === String(selfId);
                                    const isDeleted = Boolean(user.deleted_at);

                                    return (
                                        <tr key={user.id} className="transition-colors hover:bg-slate-50/70 dark:hover:bg-white/[0.02]">
                                            <td className="px-6 py-4">
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-500 dark:bg-white/[0.06] dark:text-slate-300">
                                                        {user.name?.charAt(0).toUpperCase() || '?'}
                                                    </div>
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">
                                                            {user.name}
                                                            {isSelf && (
                                                                <span className="ml-2 text-[10px] font-semibold uppercase tracking-wide text-indigo-500">
                                                                    {t('network.networkAdmin.users.you', 'You')}
                                                                </span>
                                                            )}
                                                        </p>
                                                        <p className="truncate text-xs text-slate-500 dark:text-slate-400">
                                                            {user.email}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>

                                            <td className="px-6 py-4">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    {user.is_super_admin && (
                                                        <Badge variant="violet" size="sm">
                                                            <ShieldCheck className="h-3 w-3" />
                                                            {t('network.networkAdmin.users.status.superAdmin', 'Super admin')}
                                                        </Badge>
                                                    )}
                                                    {userRoles.map((roleName) => (
                                                        <Badge key={roleName} variant={roleBadgeVariant(roleName)} size="sm">
                                                            {roleName}
                                                        </Badge>
                                                    ))}
                                                    {!user.is_super_admin && userRoles.length === 0 && (
                                                        <span className="text-xs text-slate-400 dark:text-slate-500">—</span>
                                                    )}
                                                </div>
                                            </td>

                                            <td className="px-6 py-4">
                                                {isDeleted ? (
                                                    <Badge variant="rose" size="sm" dot>
                                                        {t('network.networkAdmin.users.status.deleted', 'Deleted')}
                                                    </Badge>
                                                ) : user.email_verified_at ? (
                                                    <Badge variant="emerald" size="sm" dot>
                                                        {t('network.networkAdmin.users.status.verified', 'Verified')}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="amber" size="sm" dot>
                                                        {t('network.networkAdmin.users.status.unverified', 'Unverified')}
                                                    </Badge>
                                                )}
                                            </td>

                                            <td className="whitespace-nowrap px-6 py-4 text-xs text-slate-500 dark:text-slate-400">
                                                {formatDate(user.created_at)}
                                            </td>

                                            <td className="px-6 py-4">
                                                <div className="flex items-center justify-end gap-1">
                                                    {isDeleted ? (
                                                        <button
                                                            type="button"
                                                            title={t('network.networkAdmin.users.actions.restore', 'Restore user')}
                                                            onClick={() => restore(user)}
                                                            className="rounded-lg p-2 text-indigo-500 transition-colors hover:bg-indigo-500/10 hover:text-indigo-600 dark:text-indigo-400"
                                                        >
                                                            <RotateCcw className="h-4 w-4" />
                                                        </button>
                                                    ) : (
                                                        <>
                                                            <Link
                                                                href={route('admin.network.users.edit', { user: user.id })}
                                                                title={t('network.networkAdmin.users.actions.edit', 'Edit user')}
                                                                className="rounded-lg p-2 text-indigo-500 transition-colors hover:bg-indigo-500/10 hover:text-indigo-600 dark:text-indigo-400"
                                                            >
                                                                <Pencil className="h-4 w-4" />
                                                            </Link>

                                                            <button
                                                                type="button"
                                                                title={t('network.networkAdmin.users.actions.resetPassword', 'Reset password')}
                                                                onClick={() => {
                                                                    setResetError(null);
                                                                    setResetTarget(user);
                                                                }}
                                                                className="rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-white/[0.06]"
                                                            >
                                                                <KeyRound className="h-4 w-4" />
                                                            </button>

                                                            {!user.email_verified_at && (
                                                                <button
                                                                    type="button"
                                                                    title={t('network.networkAdmin.users.actions.resendVerification', 'Resend verification email')}
                                                                    onClick={() => resend(user)}
                                                                    className="rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-white/[0.06]"
                                                                >
                                                                    <Mail className="h-4 w-4" />
                                                                </button>
                                                            )}

                                                            <button
                                                                type="button"
                                                                title={t('network.networkAdmin.users.actions.delete', 'Delete user')}
                                                                onClick={() => setDeleteTarget(user)}
                                                                disabled={isSelf}
                                                                className="rounded-lg p-2 text-rose-500 transition-colors hover:bg-rose-500/10 hover:text-rose-600 disabled:pointer-events-none disabled:opacity-40 dark:text-rose-400"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    <Pagination meta={users.meta} onPageChange={(page) => applyFilters({ page: String(page) })} />
                </div>

                {resetTarget && (
                    <ResetPasswordModal
                        user={resetTarget}
                        isSubmitting={resetting}
                        error={resetError}
                        onSubmit={submitReset}
                        onClose={() => setResetTarget(null)}
                    />
                )}

                <ConfirmDialog
                    isOpen={deleteTarget !== null}
                    title={t('network.networkAdmin.userDelete.title', 'Delete user')}
                    description={t(
                        'network.networkAdmin.userDelete.description',
                        'Are you sure you want to delete {{name}}? The account will be moved to the deleted list and can be restored later.'
                    ).replace('{{name}}', deleteTarget?.name ?? '')}
                    confirmLabel={t('network.networkAdmin.userDelete.confirm', 'Delete')}
                    cancelLabel={t('network.networkAdmin.userDelete.cancel', 'Cancel')}
                    isLoading={deleting}
                    variant="danger"
                    onConfirm={confirmDelete}
                    onClose={() => setDeleteTarget(null)}
                />
            </div>
        </NetworkLayout>
    );
}
