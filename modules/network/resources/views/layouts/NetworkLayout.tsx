import { useState, type ReactNode } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Globe, LayoutDashboard, LogOut, Menu as MenuIcon, Users, X } from 'lucide-react';
import { route } from '@/lib/route';
import { useTranslation } from '@/hooks/useTranslation';
import type { SharedProps } from '@/types';

interface NetworkLayoutProps {
    title?: string;
    children: ReactNode;
}

const NAV_ITEMS = [
    { key: 'dashboard', href: () => route('admin.network.dashboard'), icon: LayoutDashboard, label: 'network.networkAdmin.nav.dashboard', fallback: 'Dashboard' },
    { key: 'websites', href: () => route('admin.network.websites.index'), icon: Globe, label: 'network.networkAdmin.nav.websites', fallback: 'Websites' },
    { key: 'users', href: () => route('admin.network.users.index'), icon: Users, label: 'network.networkAdmin.nav.users', fallback: 'Users' },
] as const;

function normalizePath(url: string): string {
    return (url.split('?')[0] || '/').replace(/\/$/, '') || '/';
}

export default function NetworkLayout({ title, children }: NetworkLayoutProps) {
    const { t } = useTranslation();
    const { url, props } = usePage<SharedProps>();
    const { auth, flash } = props;

    const [mobileOpen, setMobileOpen] = useState(false);
    const currentPath = normalizePath(url);

    const isActive = (href: string) => {
        const target = normalizePath(href);

        return currentPath === target || currentPath.startsWith(`${target}/`);
    };

    const sidebar = (
        <nav className="flex-1 space-y-1 overflow-y-auto p-4">
            <ul className="space-y-1">
                {NAV_ITEMS.map((item) => {
                    const href = item.href();
                    const Icon = item.icon;
                    const active = isActive(href);

                    return (
                        <li key={item.key}>
                            <Link
                                href={href}
                                onClick={() => setMobileOpen(false)}
                                className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition ${
                                    active
                                        ? 'bg-indigo-600 text-white'
                                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                                }`}
                            >
                                <Icon className="h-4 w-4 shrink-0" />
                                <span>{t(item.label, item.fallback)}</span>
                            </Link>
                        </li>
                    );
                })}
            </ul>

            <div className="pt-2">
                <Link
                    href={route('admin.websites.index')}
                    className="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                >
                    <ArrowLeft className="h-4 w-4 shrink-0" />
                    <span>{t('network.networkAdmin.backToWebsites', 'Back to websites')}</span>
                </Link>
            </div>
        </nav>
    );

    return (
        <div className="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
            <Head title={title} />

            <header className="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 dark:border-slate-800 dark:bg-slate-900">
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        className="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800 lg:hidden"
                        onClick={() => setMobileOpen(true)}
                        aria-label="Open menu"
                    >
                        <MenuIcon className="h-5 w-5" />
                    </button>
                    <Globe className="h-5 w-5 text-indigo-600" />
                    <span className="text-sm font-semibold">{t('network.networkAdmin.brandDesc', 'Network administration')}</span>
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

            <div className="flex">
                <aside className="hidden w-64 shrink-0 border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 lg:flex lg:flex-col">
                    {sidebar}
                </aside>

                {mobileOpen && (
                    <div className="fixed inset-0 z-50 flex lg:hidden">
                        <div className="absolute inset-0 bg-slate-900/50" onClick={() => setMobileOpen(false)} />
                        <aside className="relative flex w-64 flex-col bg-white dark:bg-slate-900">
                            <div className="flex h-16 items-center justify-end px-4">
                                <button type="button" onClick={() => setMobileOpen(false)} aria-label="Close menu">
                                    <X className="h-5 w-5" />
                                </button>
                            </div>
                            {sidebar}
                        </aside>
                    </div>
                )}

                <main className="min-h-[calc(100vh-4rem)] flex-1 p-4 sm:p-6 lg:p-8">
                    <div className="mx-auto w-full max-w-7xl">
                        {flash?.success && (
                            <div className="mb-6 rounded-md bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                {flash.success}
                            </div>
                        )}
                        {flash?.error && (
                            <div className="mb-6 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-400">
                                {flash.error}
                            </div>
                        )}

                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
