import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useForm } from 'react-hook-form';
import { Globe, Save } from 'lucide-react';
import AdminLayout from '@modules/admin/resources/views/layouts/AdminLayout';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import MediaField from '../components/MediaField';
import type { MediaItemSummary } from '../components/MediaPickerModal';
import { submitForm } from '@/lib/inertia-form';
import { route } from '@/lib/route';
import { useTranslation } from '@/hooks/useTranslation';
import type { SharedProps } from '@/types';

interface SettingsProps {
    title: string;
    settings: {
        title?: Record<string, string>;
        description?: Record<string, string>;
        sitename?: string | null;
        logo?: string | null;
        favicon?: string | null;
        banner?: string | null;
        user_registration?: boolean | null;
        user_verification?: boolean | null;
    };
    media: {
        logo: MediaItemSummary | null;
        favicon: MediaItemSummary | null;
        banner: MediaItemSummary | null;
    };
    locales: string[];
}

interface SettingsForm {
    title: Record<string, string>;
    description: Record<string, string>;
    sitename: string;
    logo: string | null;
    favicon: string | null;
    banner: string | null;
    user_registration: boolean;
    user_verification: boolean;
}

export default function Settings({ title, settings, media, locales }: SettingsProps) {
    const { t } = useTranslation();
    const { website_id: websiteId } = usePage<SharedProps>().props;
    const [activeLocale, setActiveLocale] = useState(locales[0] ?? 'en');

    const {
        register,
        handleSubmit,
        setError,
        setValue,
        watch,
        formState: { errors, isSubmitting },
    } = useForm<SettingsForm>({
        defaultValues: {
            title: Object.fromEntries(locales.map((locale) => [locale, settings.title?.[locale] ?? ''])),
            description: Object.fromEntries(locales.map((locale) => [locale, settings.description?.[locale] ?? ''])),
            sitename: settings.sitename ?? '',
            logo: settings.logo ?? null,
            favicon: settings.favicon ?? null,
            banner: settings.banner ?? null,
            user_registration: Boolean(settings.user_registration),
            user_verification: Boolean(settings.user_verification),
        },
    });

    const errorFor = (path: string): string | undefined =>
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        path.split('.').reduce<any>((acc, key) => acc?.[key], errors)?.message;

    const onSubmit = handleSubmit((data) =>
        submitForm(route('admin.settings.update', { websiteId }), data, { method: 'put', setError })
    );

    const branding: { key: 'logo' | 'favicon' | 'banner'; label: string }[] = [
        { key: 'logo', label: t('admin.settings.fields.logo', 'Logo') },
        { key: 'favicon', label: t('admin.settings.fields.favicon', 'Favicon') },
        { key: 'banner', label: t('admin.settings.fields.banner', 'Banner') },
    ];

    return (
        <AdminLayout title={title}>
            <h1 className="mb-6 text-2xl font-bold">{title}</h1>

            <form onSubmit={onSubmit} className="max-w-3xl space-y-6">
                <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                            <Globe className="h-4 w-4" />
                            {t('admin.settings.general.title', 'General')}
                        </h2>
                        <div className="flex gap-1 rounded-lg border border-slate-200 p-1 dark:border-slate-700">
                            {locales.map((locale) => (
                                <button
                                    key={locale}
                                    type="button"
                                    onClick={() => setActiveLocale(locale)}
                                    className={`rounded px-3 py-1 text-xs font-medium uppercase ${
                                        activeLocale === locale
                                            ? 'bg-indigo-600 text-white'
                                            : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800'
                                    }`}
                                >
                                    {locale}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="space-y-4">
                        <Input
                            label={t('admin.settings.fields.title', 'Title')}
                            error={errorFor(`title.${activeLocale}`)}
                            {...register(`title.${activeLocale}` as const, { maxLength: 255 })}
                        />

                        <div>
                            <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                                {t('admin.settings.fields.description', 'Description')}
                            </label>
                            <textarea
                                rows={3}
                                className="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3.5 py-2.5 text-sm dark:border-white/[0.08] dark:bg-slate-900/60"
                                {...register(`description.${activeLocale}` as const, { maxLength: 500 })}
                            />
                        </div>

                        <Input
                            label={t('admin.settings.fields.sitename', 'Site name')}
                            error={errors.sitename?.message}
                            {...register('sitename', { maxLength: 120 })}
                        />
                    </div>
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">
                        {t('admin.settings.branding.title', 'Branding')}
                    </h2>

                    <div className="grid gap-6 sm:grid-cols-3">
                        {branding.map(({ key, label }) => (
                            <MediaField
                                key={key}
                                label={label}
                                value={watch(key)}
                                preview={media[key]}
                                onChange={(id) => setValue(key, id)}
                            />
                        ))}
                    </div>
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">
                        {t('admin.settings.users.title', 'Users')}
                    </h2>

                    <div className="space-y-3">
                        <label className="flex items-center gap-3 text-sm">
                            <input
                                type="checkbox"
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600"
                                {...register('user_registration')}
                            />
                            {t('admin.settings.fields.userRegistration', 'Allow user registration')}
                        </label>

                        <label className="flex items-center gap-3 text-sm">
                            <input
                                type="checkbox"
                                className="h-4 w-4 rounded border-slate-300 text-indigo-600"
                                {...register('user_verification')}
                            />
                            {t('admin.settings.fields.userVerification', 'Require email verification')}
                        </label>
                    </div>
                </section>

                <Button type="submit" isLoading={isSubmitting} leftIcon={<Save className="h-4 w-4" />}>
                    {t('admin.settings.save', 'Save settings')}
                </Button>
            </form>
        </AdminLayout>
    );
}
