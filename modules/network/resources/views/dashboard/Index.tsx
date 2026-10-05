import type { ComponentType } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Globe, Users } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import { route } from '@/lib/route';
import { useTranslation } from '@/hooks/useTranslation';
import type { SharedProps } from '@/types';
import NetworkLayout from '../layouts/NetworkLayout';
import { websiteStatusVariant, type NetworkDashboard } from '../types';

interface DashboardProps {
    title: string;
    dashboard: NetworkDashboard;
}

interface StatItem {
    key: string;
    Icon: ComponentType<{ className?: string }>;
    accent: string;
    value: number;
    href: string;
}

export default function Dashboard({ title, dashboard }: DashboardProps) {
    const { t } = useTranslation();
    const { locale } = usePage<SharedProps>().props;

    const { stats, recent_websites: recentWebsites, recent_users: recentUsers } = dashboard;

    const formatDate = (value?: string | null): string =>
        value
            ? new Date(value).toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' })
            : '—';

    const statItems: StatItem[] = [
        {
            key: 'websites',
            Icon: Globe,
            accent: 'bg-indigo-500/10 text-indigo-500 border-indigo-500/20',
            value: stats.websites.total,
            href: route('admin.network.websites.index'),
        },
        {
            key: 'active',
            Icon: CheckCircle2,
            accent: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
            value: stats.websites.active,
            href: route('admin.network.websites.index'),
        },
        {
            key: 'suspended',
            Icon: AlertTriangle,
            accent: 'bg-amber-500/10 text-amber-500 border-amber-500/20',
            value: stats.websites.suspended,
            href: route('admin.network.websites.index'),
        },
        {
            key: 'users',
            Icon: Users,
            accent: 'bg-purple-500/10 text-purple-500 border-purple-500/20',
            value: stats.users.total,
            href: route('admin.network.users.index'),
        },
    ];

    return (
        <NetworkLayout title={title}>
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                    <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        {t('network.networkAdmin.dashboard.subtitle', 'Overview of every website and user across the network.')}
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    {statItems.map(({ key, Icon, accent, value, href }) => (
                        <Link
                            key={key}
                            href={href}
                            className="rounded-2xl border border-slate-200 bg-white p-5 transition-all hover:border-indigo-500/40 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-800 dark:bg-slate-900"
                        >
                            <div className={`flex h-10 w-10 items-center justify-center rounded-xl border ${accent}`}>
                                <Icon className="h-5 w-5" />
                            </div>
                            <p className="mt-4 text-2xl font-bold">{value}</p>
                            <p className="mt-0.5 text-xs font-medium text-slate-600 dark:text-slate-300">
                                {t(`network.networkAdmin.dashboard.stats.${key}`, key)}
                            </p>
                        </Link>
                    ))}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <header className="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                            <div>
                                <h2 className="text-sm font-semibold">
                                    {t('network.networkAdmin.dashboard.recentWebsites.title', 'Recent websites')}
                                </h2>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('network.networkAdmin.dashboard.recentWebsites.subtitle', 'Latest websites created on the network.')}
                                </p>
                            </div>
                            <Link
                                href={route('admin.network.websites.index')}
                                className="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                {t('network.networkAdmin.dashboard.viewAll', 'View all')}
                            </Link>
                        </header>

                        {recentWebsites.length === 0 ? (
                            <p className="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                {t('network.networkAdmin.dashboard.recentWebsites.empty', 'No websites yet.')}
                            </p>
                        ) : (
                            <ul className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {recentWebsites.map((website) => (
                                    <li key={website.id} className="flex items-center gap-3 px-5 py-3.5">
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-500 text-sm font-bold uppercase text-white">
                                            {website.title.trim().charAt(0) || '?'}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">{website.title}</p>
                                            <p className="truncate text-xs text-slate-500 dark:text-slate-400">
                                                {website.owner?.name || website.owner?.email || '—'}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-1">
                                            <Badge variant={websiteStatusVariant(website.status)} size="sm" dot>
                                                {website.status_label || website.status}
                                            </Badge>
                                            <span className="text-[10px] text-slate-400 dark:text-slate-500">
                                                {formatDate(website.created_at)}
                                            </span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <header className="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                            <div>
                                <h2 className="text-sm font-semibold">
                                    {t('network.networkAdmin.dashboard.recentUsers.title', 'Recent users')}
                                </h2>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('network.networkAdmin.dashboard.recentUsers.subtitle', 'Latest accounts created on the network.')}
                                </p>
                            </div>
                            <Link
                                href={route('admin.network.users.index')}
                                className="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                {t('network.networkAdmin.dashboard.viewAll', 'View all')}
                            </Link>
                        </header>

                        {recentUsers.length === 0 ? (
                            <p className="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                {t('network.networkAdmin.dashboard.recentUsers.empty', 'No users yet.')}
                            </p>
                        ) : (
                            <ul className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {recentUsers.map((user) => (
                                    <li key={user.id} className="flex items-center gap-3 px-5 py-3.5">
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold uppercase text-slate-700 dark:bg-white/[0.08] dark:text-slate-200">
                                            {user.name.trim().charAt(0) || '?'}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">{user.name}</p>
                                            <p className="truncate text-xs text-slate-500 dark:text-slate-400">
                                                {user.email}
                                            </p>
                                        </div>
                                        <span className="shrink-0 text-[10px] text-slate-400 dark:text-slate-500">
                                            {formatDate(user.created_at)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </NetworkLayout>
    );
}
