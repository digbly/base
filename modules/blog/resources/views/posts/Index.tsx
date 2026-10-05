import { useState, type FormEvent } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Loader2, Newspaper, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AdminLayout from '@modules/admin/resources/views/layouts/AdminLayout';
import Button from '@/components/ui/Button';
import ErrorAlert from '@/components/ui/ErrorAlert';
import Input from '@/components/ui/Input';
import { route } from '@/lib/route';
import { useTranslation } from '@/hooks/useTranslation';
import type { SharedProps } from '@/types';
import Badge from '@/components/ui/Badge';
import ConfirmDialog from '../components/ConfirmDialog';
import Pagination from '../components/Pagination';
import type { AdminPost, BlogAbilities, Paginated } from '../types';

interface PostsProps {
    title: string;
    posts: Paginated<AdminPost>;
    filters: { search: string | null; status: string | null };
    abilities: BlogAbilities;
}

export default function Posts({ title, posts, filters, abilities }: PostsProps) {
    const { t } = useTranslation();
    const { locale } = usePage<SharedProps>().props;

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [deleteTarget, setDeleteTarget] = useState<AdminPost | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const indexUrl = route('admin.blog.posts.index');

    const applyFilters = (overrides: Record<string, string | null> = {}) => {
        const params: Record<string, string> = {};
        const merged = { search, status, ...overrides };

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
            ? new Date(value).toLocaleDateString(locale, {
                  year: 'numeric',
                  month: 'short',
                  day: 'numeric',
              })
            : '—';

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setError(null);
        setDeleting(true);

        router.delete(route('admin.blog.posts.destroy', { post: deleteTarget.id }), {
            preserveScroll: true,
            onError: () => setError(t('blog.posts.errors.deleteFailed', 'Failed to delete the post.')),
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    return (
        <AdminLayout title={title}>
            <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                    <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        {t('blog.posts.subtitle', 'Write, publish and organise your articles.')}
                    </p>
                </div>

                {abilities.create && (
                    <Link href={route('admin.blog.posts.create')}>
                        <Button leftIcon={<Plus className="h-4 w-4" />}>
                            {t('blog.posts.add', 'Add post')}
                        </Button>
                    </Link>
                )}
            </div>

            {error && (
                <div className="mb-4">
                    <ErrorAlert message={error} />
                </div>
            )}

            <form onSubmit={onSearch} className="mb-4 flex flex-wrap gap-3">
                <div className="min-w-[200px] flex-1">
                    <Input
                        placeholder={t('blog.posts.searchPlaceholder', 'Search posts')}
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
                    <option value="">{t('blog.posts.filters.allStatuses', 'All statuses')}</option>
                    <option value="draft">{t('blog.posts.filters.draft', 'Draft')}</option>
                    <option value="published">{t('blog.posts.filters.published', 'Published')}</option>
                </select>

                <Button type="submit" variant="secondary">
                    {t('blog.posts.filters.search', 'Search')}
                </Button>
            </form>

            <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="overflow-x-auto">
                    <table className="w-full border-collapse text-left text-sm">
                        <thead className="border-b border-slate-200 text-xs uppercase text-slate-500 dark:border-slate-800">
                            <tr>
                                <th className="px-6 py-3 font-semibold">{t('blog.posts.table.post', 'Post')}</th>
                                <th className="px-6 py-3 font-semibold">{t('blog.posts.table.status', 'Status')}</th>
                                <th className="px-6 py-3 font-semibold">
                                    {t('blog.posts.table.categories', 'Categories')}
                                </th>
                                <th className="px-6 py-3 font-semibold">{t('blog.posts.table.created', 'Created')}</th>
                                <th className="px-6 py-3 text-right font-semibold">
                                    {t('blog.posts.table.actions', 'Actions')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                            {posts.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-6 py-12 text-center">
                                        <div className="flex flex-col items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                            <Newspaper className="h-6 w-6 text-slate-400" />
                                            <span>{t('blog.posts.empty', 'No posts match your filters.')}</span>
                                        </div>
                                    </td>
                                </tr>
                            )}

                            {posts.data.map((post) => (
                                <tr key={post.id} className="transition-colors hover:bg-slate-50/70 dark:hover:bg-white/[0.02]">
                                    <td className="px-6 py-4">
                                        <p className="max-w-xs truncate text-sm font-medium text-slate-900 dark:text-white">
                                            {post.title ?? '—'}
                                        </p>
                                        <p className="max-w-xs truncate text-xs text-slate-500 dark:text-slate-400">
                                            /{post.slug ?? ''}
                                        </p>
                                    </td>

                                    <td className="px-6 py-4">
                                        <Badge variant={post.status === 'published' ? 'emerald' : 'amber'} size="sm" dot>
                                            {post.status_label}
                                        </Badge>
                                    </td>

                                    <td className="px-6 py-4">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            {post.categories.length === 0 ? (
                                                <span className="text-xs text-slate-400 dark:text-slate-500">—</span>
                                            ) : (
                                                post.categories.map((category) => (
                                                    <Badge key={category.id} variant="slate" size="sm">
                                                        {category.name}
                                                    </Badge>
                                                ))
                                            )}
                                        </div>
                                    </td>

                                    <td className="whitespace-nowrap px-6 py-4 text-xs text-slate-500 dark:text-slate-400">
                                        {formatDate(post.created_at)}
                                    </td>

                                    <td className="px-6 py-4">
                                        <div className="flex items-center justify-end gap-1">
                                            {abilities.update && (
                                                <Link
                                                    href={route('admin.blog.posts.edit', { post: post.id })}
                                                    title={t('blog.posts.actions.edit', 'Edit post')}
                                                    className="rounded-lg p-2 text-indigo-500 transition-colors hover:bg-indigo-500/10 hover:text-indigo-600 dark:text-indigo-400"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            )}

                                            {abilities.delete && (
                                                <button
                                                    type="button"
                                                    title={t('blog.posts.actions.delete', 'Delete post')}
                                                    onClick={() => setDeleteTarget(post)}
                                                    className="rounded-lg p-2 text-rose-500 transition-colors hover:bg-rose-500/10 hover:text-rose-600 dark:text-rose-400"
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

                <Pagination
                    meta={posts.meta}
                    onPageChange={(page) => applyFilters({ page: String(page) })}
                />
            </div>

            <ConfirmDialog
                isOpen={deleteTarget !== null}
                title={t('blog.posts.deleteDialog.title', 'Delete post')}
                description={t(
                    'blog.posts.deleteDialog.description',
                    'Are you sure you want to delete "{{title}}"? This cannot be undone.'
                ).replace('{{title}}', deleteTarget?.title ?? '')}
                confirmLabel={t('blog.posts.deleteDialog.confirm', 'Delete')}
                cancelLabel={t('blog.posts.deleteDialog.cancel', 'Cancel')}
                isLoading={deleting}
                variant="danger"
                onConfirm={confirmDelete}
                onClose={() => setDeleteTarget(null)}
            />
        </AdminLayout>
    );
}
