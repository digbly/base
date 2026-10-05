import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Globe, LogOut, Plus, ShieldCheck, Sparkles, Users } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { route } from '@/lib/route';
import { useTranslation } from '@/hooks/useTranslation';
import type { SharedProps } from '@/types';
import { websiteStatusVariant, type Website } from '../types';
import OwnWebsiteFormModal, { type WebsiteFormValues } from './components/OwnWebsiteFormModal';

interface WebsitePickerProps {
    title: string;
    websites: Website[];
    networkDomain: string | null;
    canCreate: boolean;
    isSuperAdmin: boolean;
}

export default function WebsitePicker({ title, websites, networkDomain, canCreate, isSuperAdmin }: WebsitePickerProps) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedProps>().props;

    const [formOpen, setFormOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit = (values: WebsiteFormValues) => {
        setError(null);

        router.post(
            route('admin.websites.store'),
            values as unknown as Parameters<typeof router.post>[1],
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setFormOpen(false),
                onError: (errors: Record<string, string>) =>
                    setError(
                        Object.values(errors)[0] ||
                            t('network.network.errors.createFailed', 'We couldn\'t create the website. Please try again.')
                    ),
            }
        );
    };

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
            <Head title={title} />

            <div className="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
                <div className="absolute -left-40 -top-40 h-96 w-96 rounded-full bg-indigo-500/15 blur-3xl dark:bg-indigo-600/10" />
                <div className="absolute -right-40 top-1/3 h-96 w-96 rounded-full bg-purple-500/15 blur-3xl dark:bg-purple-600/10" />
            </div>

            <header className="mx-auto flex h-20 w-full max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-purple-500 text-white shadow-md shadow-indigo-500/25">
                        <Sparkles className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="flex items-center gap-1.5 text-lg font-extrabold tracking-tight">
                            <span>Admin</span>
                        </div>
                        <p className="text-[11px] text-slate-500 dark:text-slate-400">
                            {t('network.network.subtitle', 'Select a website to manage its dashboard, users and settings.')}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-4">
                    <span className="hidden text-sm text-slate-500 dark:text-slate-400 sm:inline">
                        {auth.user?.name}
                    </span>
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                        className="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        <LogOut className="h-4 w-4" />
                        <span className="hidden sm:inline">Logout</span>
                    </button>
                </div>
            </header>

            <main className="mx-auto w-full max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">{title}</h1>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {t('network.network.subtitle', 'Select a website to manage its dashboard, users and settings.')}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        {isSuperAdmin && (
                            <Link href={route('admin.network.dashboard')}>
                                <Button variant="outline" leftIcon={<ShieldCheck className="h-4 w-4" />}>
                                    {t('network.networkAdmin.entry', 'Network administration')}
                                </Button>
                            </Link>
                        )}

                        {canCreate && (
                            <Button onClick={() => setFormOpen(true)} leftIcon={<Plus className="h-4 w-4" />}>
                                {t('network.network.create', 'New website')}
                            </Button>
                        )}
                    </div>
                </div>

                {websites.length === 0 ? (
                    <div className="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-500/20 bg-slate-500/10 text-slate-500">
                            <Globe className="h-6 w-6" />
                        </div>
                        <h2 className="mt-4 text-base font-semibold">
                            {t('network.network.empty.title', 'No websites yet')}
                        </h2>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {t('network.network.empty.message', 'You are not a member of any website yet. Create your first website to get started.')}
                        </p>
                        {canCreate && (
                            <div className="mt-5 flex justify-center">
                                <Button onClick={() => setFormOpen(true)} leftIcon={<Plus className="h-4 w-4" />}>
                                    {t('network.network.create', 'New website')}
                                </Button>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {websites.map((website) => (
                            <Link
                                key={website.id}
                                href={route('admin.dashboard', { websiteId: website.id })}
                                className="flex h-full flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition-all hover:border-indigo-500/40 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-500 font-bold uppercase text-white">
                                        {website.title.trim().charAt(0) || '?'}
                                    </div>
                                    <Badge variant={websiteStatusVariant(website.status)} size="sm" dot>
                                        {website.status_label || website.status}
                                    </Badge>
                                </div>

                                <div className="min-w-0">
                                    <h3 className="truncate text-sm font-semibold">{website.title}</h3>
                                    <p className="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                                        {website.domain || website.subdomain}
                                    </p>
                                </div>

                                <div className="mt-auto flex items-center justify-between gap-3 border-t border-slate-100 pt-2 dark:border-slate-800">
                                    <span className="inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                        <Users className="h-3.5 w-3.5" />
                                        {t('network.network.usersCount', '{{total}} members').replace(
                                            '{{total}}',
                                            String(website.users_count ?? 0)
                                        )}
                                    </span>
                                    <span className="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                        {t('network.network.open', 'Manage')}
                                        <ArrowRight className="h-3.5 w-3.5" />
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </main>

            {formOpen && (
                <OwnWebsiteFormModal
                    networkDomain={networkDomain}
                    isSubmitting={processing}
                    error={error}
                    onSubmit={submit}
                    onClose={() => setFormOpen(false)}
                />
            )}
        </div>
    );
}
