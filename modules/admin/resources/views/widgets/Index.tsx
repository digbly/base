import { usePage } from '@inertiajs/react';
import AdminLayout from '@modules/admin/resources/views/layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import type { SharedProps } from '@/types';
import WidgetsEditor from './components/WidgetsEditor';
import type { SidebarDefinition, SidebarWidgetItem, WidgetDefinition } from './types';

interface WidgetsProps {
    title: string;
    widgets: WidgetDefinition[];
    sidebars: SidebarDefinition[];
    sidebar_widgets: Record<string, SidebarWidgetItem[]>;
    locale: string;
    theme: string | null;
    abilities: { update: boolean };
}

export default function Widgets({
    title,
    widgets,
    sidebars,
    sidebar_widgets,
    locale,
    theme,
    abilities,
}: WidgetsProps) {
    const { t } = useTranslation();

    return (
        <AdminLayout title={title}>
            <div className="mb-6">
                <h1 className="text-xl font-bold tracking-tight sm:text-2xl">{title}</h1>
                <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {t('admin.widgets.subtitle', 'Manage the widgets displayed in your theme sidebars.')}
                </p>
            </div>

            <WidgetsEditor
                key={JSON.stringify(sidebar_widgets)}
                widgets={widgets}
                sidebars={sidebars}
                sidebarWidgets={sidebar_widgets}
                theme={theme}
                locale={locale}
                                canUpdate={abilities.update}
            />
        </AdminLayout>
    );
}
