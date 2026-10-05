import { useState, type FormEvent } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Globe, Pencil, Plus, Search, Trash2, Users } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import ErrorAlert from '@/components/ui/ErrorAlert';
import Input from '@/components/ui/Input';
import { route } from '@/lib/route';
import { useTranslation } from '@/hooks/useTranslation';
import type { SharedProps } from '@/types';
import ConfirmDialog from '../components/ConfirmDialog';
import Pagination from '../components/Pagination';
import NetworkLayout from '../layouts/NetworkLayout';
import {
    websiteStatusVariant,
    WEBSITE_STATUSES,
    type AdminUser,
    type Paginated,
    type Website,
} from '../types';
import WebsiteFormModal, { type WebsiteFormValues } from './components/WebsiteFormModal';

interface WebsitesProps {
    title: string;
    websites: Paginated<Website>;
    filters: { q: string | null; status: string | null };
    owners: AdminUser[];
    networkDomain: string | null;
}

const firstError = (errors: Record<string, string>): string => Object.values(errors)[0] ?? '';

export default function Websites({ title, websites, filters, owners, networkDomain }: WebsitesProps) {
    const { t } = useTranslation();
    const { locale } = usePage<SharedProps>().props;

    const [search, setSearch] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [formOpen, setFormOpen] = useState(false);
    const [formWebsite, setFormWebsite] = useState<Website | null>(null);
    const [formError, setFormError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<Website | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const indexUrl = route('admin.network.websites.index');

    const applyFilters = (overrides: Record<string, string | null> = {}) => {
        const params: Record<string, string> = {};
        const merged = { q: search, status, ...overrides };

        Object.entries(merged).forEach(([key, value]) => {
            if (value) {
                params[key] = value;
            }
        });

        router.get(indexUrl, params, { preserveState: true, replace: true });
    };

    const onSearch = (event: FormEvent) => {
        event.preventDefault();
        applyFilters({ q: search });
    };

    const formatDate = (value?: string | null): string =>
        value
            ? new Date(value).toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' })
            : '—';

    const openCreate = () => {
        setFormWebsite(null);
        setFormError(null);
        setFormOpen(true);
    };

    const openEdit = (website: Website) => {
        setFormWebsite(website);
        setFormError(null);
        setFormOpen(true);
    };

    const closeForm = () => {
        setFormOpen(false);
        setFormWebsite(null);
        setFormError(null);
    };

    const submitForm = (values: WebsiteFormValues) => {
        setFormError(null);

        const payload = {
            title: values.title,
            subdomain: values.subdomain,
            status: values.status,
            user_id: values.user_id,
            domain: values.domain || null,
            description: values.description || null,
        };

        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => closeForm(),
            onError: (errors: Record<string, string>) =>
                setFormError(firstError(errors) || t('network.networkAdmin.errors.saveFailed', 'Failed to save. Please try again.')),
        };

        if (formWebsite) {
            router.put(
                route('admin.network.websites.update', { website: formWebsite.id }),
                payload as unknown as Parameters<typeof router.put>[1],
                options
            );
        } else {
            router.post(
                route('admin.network.websites.store'),
                payload as unknown as Parameters<typeof router.post>[1],
                options
            );
        }
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setError(null);
        setDeleting(true);

        router.delete(route('admin.network.websites.destroy', { website: deleteTarget.id }), {
            preserveScroll: true,
            onError: () => setError(t('network.networkAdmin.errors.deleteFailed', 'Failed to delete. Please try again.')),
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    return (
        <NetworkLayout title={title}>
            <div className="space-y-5">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                    <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        {t('network.networkAdmin.websites.subtitle', 'Manage every website on the network.')}
                    </p>
                </div>

                {error && <ErrorAlert message={error} />}

                <div className="flex justify-end">
                    <Button onClick={openCreate} leftIcon={<Plus className="h-4 w-4" />}>
                        {t('network.networkAdmin.websites.add', 'New website')}
                    </Button>
                </div>

                <form onSubmit={onSearch} className="flex flex-wrap gap-3">
                    <div className="min-w-[200px] flex-1">
                        <Input
                            placeholder={t('network.networkAdmin.websites.searchPlaceholder', 'Search by title, domain or subdomain')}
                            leftIcon={<Search className="h-4 w-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    <select
                        value={status}
                        onChange={(event) => {
                            setStatus(event.target.value);
                            applyFilters({ status: event.target.value });
                        }}
                        className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <option value="">{t('network.networkAdmin.websites.allStatuses', 'All statuses')}</option>
                        {WEBSITE_STATUSES.map((item) => (
                            <option key={item} value={item}>
                                {t(`network.network.status.${item}`, item)}
                            </option>
                        ))}
                    </select>

                    <Button type="submit" variant="secondary">
                        {t('network.networkAdmin.websites.search', 'Search')}
                    </Button>
                </form>

                <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-left text-sm">
                            <thead className="border-b border-slate-200 text-xs uppercase text-slate-500 dark:border-slate-800">
                                <tr>
                                    <th className="px-6 py-3 font-semibold">{t('network.networkAdmin.websites.table.website', 'Website')}</th>
                                    <th className="px-6 py-3 font-semibold">{t('network.networkAdmin.websites.table.owner', 'Owner')}</th>
                                    <th className="px-6 py-3 font-semibold">{t('network.networkAdmin.websites.table.status', 'Status')}</th>
                                    <th className="px-6 py-3 font-semibold">{t('network.networkAdmin.websites.table.members', 'Members')}</th>
                                    <th className="px-6 py-3 font-semibold">{t('network.networkAdmin.websites.table.created', 'Created')}</th>
                                    <th className="px-6 py-3 text-right font-semibold">{t('network.networkAdmin.websites.table.actions', 'Actions')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {websites.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-12 text-center">
                                            <div className="flex flex-col items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                                <Globe className="h-6 w-6 text-slate-400" />
                                                <span>{t('network.networkAdmin.websites.empty', 'No websites match your filters.')}</span>
                                            </div>
                                        </td>
                                    </tr>
                                )}

                                {websites.data.map((website) => (
                                    <tr key={website.id} className="transition-colors hover:bg-slate-50/70 dark:hover:bg-white/[0.02]">
                                        <td className="px-6 py-4">
                                            <div className="flex min-w-0 items-center gap-3">
                                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-500 text-sm font-bold uppercase text-white">
                                                    {website.title.trim().charAt(0) || '?'}
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">{website.title}</p>
                                                    <p className="truncate text-xs text-slate-500 dark:text-slate-400">
                                                        {website.domain || `${website.subdomain}.`}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <td className="px-6 py-4">
                                            <p className="truncate text-sm text-slate-700 dark:text-slate-200">
                                                {website.owner?.name ?? '—'}
                                            </p>
                                            <p className="truncate text-xs text-slate-500 dark:text-slate-400">
                                                {website.owner?.email ?? ''}
                                            </p>
                                        </td>

                                        <td className="px-6 py-4">
                                            <Badge variant={websiteStatusVariant(website.status)} size="sm" dot>
                                                {website.status_label || website.status}
                                            </Badge>
                                        </td>

                                        <td className="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                            <span className="inline-flex items-center gap-1.5">
                                                <Users className="h-3.5 w-3.5" />
                                                {website.users_count ?? 0}
                                            </span>
                                        </td>

                                        <td className="whitespace-nowrap px-6 py-4 text-xs text-slate-500 dark:text-slate-400">
                                            {formatDate(website.created_at)}
                                        </td>

                                        <td className="px-6 py-4">
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    title={t('network.networkAdmin.websites.actions.edit', 'Edit website')}
                                                    onClick={() => openEdit(website)}
                                                    className="rounded-lg p-2 text-indigo-500 transition-colors hover:bg-indigo-500/10 hover:text-indigo-600 dark:text-indigo-400"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>

                                                <button
                                                    type="button"
                                                    title={t('network.networkAdmin.websites.actions.delete', 'Delete website')}
                                                    onClick={() => setDeleteTarget(website)}
                                                    className="rounded-lg p-2 text-rose-500 transition-colors hover:bg-rose-500/10 hover:text-rose-600 dark:text-rose-400"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <Pagination meta={websites.meta} onPageChange={(page) => applyFilters({ page: String(page) })} />
                </div>

                {formOpen && (
                    <WebsiteFormModal
                        website={formWebsite}
                        owners={owners}
                        networkDomain={networkDomain}
                        isSubmitting={processing}
                        error={formError}
                        onSubmit={submitForm}
                        onClose={closeForm}
                    />
                )}

                <ConfirmDialog
                    isOpen={deleteTarget !== null}
                    title={t('network.networkAdmin.websiteDelete.title', 'Delete website')}
                    description={t(
                        'network.networkAdmin.websiteDelete.description',
                        'Are you sure you want to delete {{name}}? This action cannot be undone.'
                    ).replace('{{name}}', deleteTarget?.title ?? '')}
                    confirmLabel={t('network.networkAdmin.websiteDelete.confirm', 'Delete')}
                    cancelLabel={t('network.networkAdmin.websiteDelete.cancel', 'Cancel')}
                    isLoading={deleting}
                    variant="danger"
                    onConfirm={confirmDelete}
                    onClose={() => setDeleteTarget(null)}
                />
            </div>
        </NetworkLayout>
    );
}
