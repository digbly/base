import { useRef, useState, type FormEvent } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Controller, useForm } from 'react-hook-form';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AdminLayout from '@modules/admin/resources/views/layouts/AdminLayout';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import Modal from '@/components/ui/Modal';
import RichTextEditor from '@/components/ui/RichTextEditor';
import MediaPickerModal from '../components/MediaPickerModal';
import { submitForm } from '@/lib/inertia-form';
import { route } from '@/lib/route';
import { useTranslation } from '@/hooks/useTranslation';
import type { SharedProps } from '@/types';

interface PageRow {
    id: string;
    title: string;
    slug: string;
    content: string | null;
    description: string | null;
    status: 'published' | 'draft';
    template: string | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PagesProps {
    title: string;
    pages: {
        data: PageRow[];
        links: PaginationLink[];
        meta: { current_page: number; last_page: number; total: number };
    };
    filters: { search: string | null; status: string | null };
    abilities: { create: boolean; update: boolean; delete: boolean };
}

interface PageForm {
    title: string;
    slug: string;
    content: string;
    description: string;
    status: 'published' | 'draft';
    template: string;
}

const emptyForm: PageForm = { title: '', slug: '', content: '', description: '', status: 'published', template: '' };

const slugify = (value: string): string =>
    value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');

const pageLabel = (label: string): string => label.replace(/&laquo;/g, '«').replace(/&raquo;/g, '»');

export default function Pages({ title, pages, filters, abilities }: PagesProps) {
    const { t } = useTranslation();
    const { website_id: websiteId, locale } = usePage<SharedProps>().props;

    const [search, setSearch] = useState(filters.search ?? '');
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<PageRow | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<PageRow | null>(null);
    const [mediaOpen, setMediaOpen] = useState(false);
    const insertImageRef = useRef<((url: string, alt?: string) => void) | null>(null);

    const indexUrl = route('admin.pages.index', { websiteId });

    const form = useForm<PageForm>({ defaultValues: emptyForm });

    const applyFilters = (overrides: Record<string, string | null> = {}) => {
        const merged = { search, status: filters.status, ...overrides };
        const params: Record<string, string> = {};

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

    const openCreate = () => {
        setEditing(null);
        form.reset(emptyForm);
        setFormOpen(true);
    };

    const openEdit = (page: PageRow) => {
        setEditing(page);
        form.reset({
            title: page.title,
            slug: page.slug,
            content: page.content ?? '',
            description: page.description ?? '',
            status: page.status,
            template: page.template ?? '',
        });
        setFormOpen(true);
    };

    const submit = form.handleSubmit((data) => {
        const payload = {
            ...data,
            slug: data.slug.trim() || slugify(data.title),
            locale,
        };

        const url = editing
            ? route('admin.pages.update', { websiteId, page: editing.id })
            : route('admin.pages.store', { websiteId });

        return submitForm(url, payload, {
            method: editing ? 'put' : 'post',
            setError: form.setError,
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
        });
    });

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(route('admin.pages.destroy', { websiteId, page: deleteTarget.id }), {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    return (
        <AdminLayout title={title}>
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-bold">{title}</h1>
                {abilities.create && (
                    <Button onClick={openCreate} leftIcon={<Plus className="h-4 w-4" />}>
                        {t('admin.pages.addPage', 'Add page')}
                    </Button>
                )}
            </div>

            <form onSubmit={onSearch} className="mb-4 flex flex-wrap gap-3">
                <div className="min-w-[200px] flex-1">
                    <Input
                        placeholder={t('admin.pages.filters.searchPlaceholder', 'Search pages...')}
                        leftIcon={<Search className="h-4 w-4" />}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </div>
                <select
                    value={filters.status ?? ''}
                    onChange={(event) => applyFilters({ status: event.target.value })}
                    className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                >
                    <option value="">{t('admin.pages.filters.allStatuses', 'All statuses')}</option>
                    <option value="published">{t('admin.pages.status.published', 'Published')}</option>
                    <option value="draft">{t('admin.pages.status.draft', 'Draft')}</option>
                </select>
                <Button type="submit" variant="secondary">
                    {t('admin.pages.filters.search', 'Search')}
                </Button>
            </form>

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-slate-200 text-xs uppercase text-slate-500 dark:border-slate-800">
                        <tr>
                            <th className="px-4 py-3">{t('admin.pages.form.title', 'Title')}</th>
                            <th className="px-4 py-3">{t('admin.pages.form.slug', 'Slug')}</th>
                            <th className="px-4 py-3">{t('admin.pages.form.status', 'Status')}</th>
                            <th className="px-4 py-3 text-right">{t('admin.users.table.actions', 'Actions')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {pages.data.length === 0 && (
                            <tr>
                                <td colSpan={4} className="px-4 py-8 text-center text-slate-500">
                                    {t('admin.pages.empty', 'No pages found.')}
                                </td>
                            </tr>
                        )}

                        {pages.data.map((page) => (
                            <tr key={page.id} className="border-b border-slate-100 last:border-0 dark:border-slate-800/60">
                                <td className="px-4 py-3 font-medium">{page.title}</td>
                                <td className="px-4 py-3 text-slate-500">/{page.slug}</td>
                                <td className="px-4 py-3">
                                    <span
                                        className={`rounded px-2 py-0.5 text-xs ${
                                            page.status === 'published'
                                                ? 'bg-emerald-500/10 text-emerald-600'
                                                : 'bg-amber-500/10 text-amber-600'
                                        }`}
                                    >
                                        {page.status === 'published'
                                            ? t('admin.pages.status.published', 'Published')
                                            : t('admin.pages.status.draft', 'Draft')}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-1">
                                        {abilities.update && (
                                            <button
                                                type="button"
                                                onClick={() => openEdit(page)}
                                                className="rounded p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                        )}
                                        {abilities.delete && (
                                            <button
                                                type="button"
                                                onClick={() => setDeleteTarget(page)}
                                                className="rounded p-2 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {pages.meta.last_page > 1 && (
                <div className="mt-4 flex justify-center gap-1">
                    {pages.links.map((link, index) =>
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
                open={formOpen}
                title={editing ? t('admin.pages.form.editTitle', 'Edit page') : t('admin.pages.form.createTitle', 'Create page')}
                onClose={() => setFormOpen(false)}
            >
                <form onSubmit={submit} className="space-y-4" noValidate>
                    <Input
                        label={t('admin.pages.form.title', 'Title')}
                        error={form.formState.errors.title?.message}
                        {...form.register('title', { required: t('admin.pages.form.titleRequired', 'Title is required') })}
                    />
                    <Input
                        label={t('admin.pages.form.slug', 'Slug')}
                        error={form.formState.errors.slug?.message}
                        {...form.register('slug')}
                    />
                    <div>
                        <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                            {t('admin.pages.form.content', 'Content')}
                        </label>
                        <Controller
                            control={form.control}
                            name="content"
                            render={({ field }) => (
                                <RichTextEditor
                                    value={field.value}
                                    onChange={field.onChange}
                                    onRequestMedia={(insert) => {
                                        insertImageRef.current = insert;
                                        setMediaOpen(true);
                                    }}
                                    mediaLabel={t('admin.media.insertImage', 'Insert image')}
                                />
                            )}
                        />
                    </div>
                    <Input
                        label={t('admin.pages.form.description', 'Description')}
                        error={form.formState.errors.description?.message}
                        {...form.register('description', { maxLength: 500 })}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                                {t('admin.pages.form.status', 'Status')}
                            </label>
                            <select
                                className="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3.5 py-2.5 text-sm dark:border-white/[0.08] dark:bg-slate-900/60"
                                {...form.register('status')}
                            >
                                <option value="published">{t('admin.pages.status.published', 'Published')}</option>
                                <option value="draft">{t('admin.pages.status.draft', 'Draft')}</option>
                            </select>
                        </div>
                        <Input
                            label={t('admin.pages.form.template', 'Template')}
                            placeholder="landing"
                            error={form.formState.errors.template?.message}
                            {...form.register('template', { maxLength: 100 })}
                        />
                    </div>

                    <div className="flex justify-end gap-3 pt-2">
                        <Button variant="outline" onClick={() => setFormOpen(false)}>
                            {t('admin.pages.form.cancel', 'Cancel')}
                        </Button>
                        <Button type="submit" isLoading={form.formState.isSubmitting}>
                            {editing ? t('admin.pages.form.save', 'Save') : t('admin.pages.form.create', 'Create')}
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal
                open={deleteTarget !== null}
                title={t('admin.pages.deleteDialog.title', 'Delete page')}
                onClose={() => setDeleteTarget(null)}
            >
                <p className="mb-5 text-sm text-slate-600 dark:text-slate-300">
                    {t(
                        'admin.pages.deleteDialog.description',
                        'Are you sure you want to delete this page? This cannot be undone.'
                    ).replace('{{name}}', deleteTarget?.title ?? '')}
                </p>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={() => setDeleteTarget(null)}>
                        {t('admin.pages.deleteDialog.cancel', 'Cancel')}
                    </Button>
                    <Button variant="danger" onClick={confirmDelete}>
                        {t('admin.pages.deleteDialog.confirm', 'Delete')}
                    </Button>
                </div>
            </Modal>

            <MediaPickerModal
                open={mediaOpen}
                onClose={() => setMediaOpen(false)}
                onSelect={(item) => {
                    if (item.url) {
                        insertImageRef.current?.(item.url, item.title ?? item.file_name ?? undefined);
                    }
                    setMediaOpen(false);
                }}
            />
        </AdminLayout>
    );
}
