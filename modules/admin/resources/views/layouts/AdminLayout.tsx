import { useState, type ReactNode } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, Globe, LogOut, Menu as MenuIcon, X } from 'lucide-react';
import NavIcon from '@/components/NavIcon';
import { route } from '@/lib/route';
import type { NavItem, SharedProps } from '@/types';

interface AdminLayoutProps {
    title?: string;
    children: ReactNode;
}

function joinUrl(base: string, to?: string | null): string {
    if (!to) {
        return base;
    }

    const path = to.replace(/^\//, '');

    if (path === '') {
        return base;
    }

    return `${base.replace(/\/$/, '')}/${path}`;
}

function normalizePath(url: string): string {
    return (url.split('?')[0] || '/').replace(/\/$/, '') || '/';
}

export default function AdminLayout({ title, children }: AdminLayoutProps) {
    const page = usePage<SharedProps>();
    const { url, props } = page;
    const { auth, admin_menu: menu, admin_prefix: prefix, flash } = props;

    const base = `/${prefix}`;
    const currentPath = normalizePath(url);
    const isActive = (to?: string | null) => {
        if (!to) {
            return false;
        }

        const target = normalizePath(joinUrl(base, to));

        return currentPath === target || currentPath.startsWith(`${target}/`);
    };

    const [mobileOpen, setMobileOpen] = useState(false);
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    const toggle = (key: string) => {
        setExpanded((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    const logout = () => {
        router.post('/logout');
    };

    const renderItem = (item: NavItem) => {
        const active = isActive(item.to) || item.children.some((child) => isActive(child.to));
        const hasChildren = item.children.length > 0;
        const isOpen = expanded[item.key] ?? active;

        return (
            <li key={item.key}>
                <div
                    className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition ${
                        active
                            ? 'bg-indigo-600 text-white'
                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                    }`}
                >
                    {item.to ? (
                        <Link href={joinUrl(base, item.to)} className="flex flex-1 items-center gap-3">
                            <NavIcon name={item.icon} className="h-4 w-4 shrink-0" />
                            <span>{item.label}</span>
                        </Link>
                    ) : (
                        <button
                            type="button"
                            onClick={() => toggle(item.key)}
                            className="flex flex-1 items-center gap-3 text-left"
                        >
                            <NavIcon name={item.icon} className="h-4 w-4 shrink-0" />
                            <span>{item.label}</span>
                        </button>
                    )}

                    {hasChildren && (
                        <button type="button" onClick={() => toggle(item.key)} aria-label="Toggle group">
                            <ChevronDown className={`h-4 w-4 transition ${isOpen ? 'rotate-180' : ''}`} />
                        </button>
                    )}
                </div>

                {hasChildren && isOpen && (
                    <ul className="mt-1 space-y-1 border-l border-slate-200 pl-4 dark:border-slate-800">
                        {item.children.map((child) => {
                            const childActive = isActive(child.to);

                            return (
                                <li key={child.key}>
                                    <Link
                                        href={joinUrl(base, child.to)}
                                        className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm transition ${
                                            childActive
                                                ? 'text-indigo-600 dark:text-indigo-400'
                                                : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100'
                                        }`}
                                    >
                                        <NavIcon name={child.icon} className="h-4 w-4 shrink-0" />
                                        <span>{child.label}</span>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </li>
        );
    };

    const sidebar = (
        <nav className="flex-1 space-y-1 overflow-y-auto p-4">
            <ul className="space-y-1">{menu.map(renderItem)}</ul>
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
                    <Link href={route('admin.dashboard')} className="flex items-center gap-3">
                        <Globe className="h-5 w-5 text-indigo-600" />
                        <span className="text-sm font-semibold">Admin</span>
                    </Link>
                </div>

                <div className="flex items-center gap-4">
                    <Link
                        href="/profile"
                        className="hidden text-sm text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100 sm:inline"
                    >
                        {auth.user?.name}
                    </Link>
                    <button
                        type="button"
                        onClick={logout}
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
                        <div
                            className="absolute inset-0 bg-slate-900/50"
                            onClick={() => setMobileOpen(false)}
                        />
                        <aside className="relative flex w-64 flex-col bg-white dark:bg-slate-900">
                            <div className="flex h-16 items-center justify-end px-4">
                                <button
                                    type="button"
                                    onClick={() => setMobileOpen(false)}
                                    aria-label="Close menu"
                                >
                                    <X className="h-5 w-5" />
                                </button>
                            </div>
                            {sidebar}
                        </aside>
                    </div>
                )}

                <main className="min-h-[calc(100vh-4rem)] flex-1 p-4 sm:p-6 lg:p-8">
                    {(flash?.success || flash?.error || flash?.warning) && (
                        <div className="mb-6 space-y-2">
                            {flash.success && (
                                <div className="rounded-md bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                    {flash.success}
                                </div>
                            )}
                            {flash.error && (
                                <div className="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-400">
                                    {flash.error}
                                </div>
                            )}
                            {flash.warning && (
                                <div className="rounded-md bg-amber-50 p-3 text-sm text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">
                                    {flash.warning}
                                </div>
                            )}
                        </div>
                    )}

                    {children}
                </main>
            </div>
        </div>
    );
}
