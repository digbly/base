import { useState, type FormEvent } from 'react';
import Button from '@/components/ui/Button';
import ErrorAlert from '@/components/ui/ErrorAlert';
import Input from '@/components/ui/Input';
import Modal from '@/components/ui/Modal';
import { useTranslation } from '@/hooks/useTranslation';
import { WEBSITE_STATUSES, type WebsiteStatus } from '../../types';

const SUBDOMAIN_PATTERN = /^[a-zA-Z0-9_-]+$/;

export interface WebsiteFormValues {
    title: string;
    subdomain: string;
    description: string;
    status: WebsiteStatus;
}

interface WebsiteFormModalProps {
    networkDomain: string | null;
    isSubmitting: boolean;
    error: string | null;
    onSubmit: (values: WebsiteFormValues) => void;
    onClose: () => void;
}

export default function OwnWebsiteFormModal({
    networkDomain,
    isSubmitting,
    error,
    onSubmit,
    onClose,
}: WebsiteFormModalProps) {
    const { t } = useTranslation();

    const [values, setValues] = useState<WebsiteFormValues>({
        title: '',
        subdomain: '',
        description: '',
        status: 'active',
    });
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

    const setField = <K extends keyof WebsiteFormValues>(field: K, value: WebsiteFormValues[K]) => {
        setValues((previous) => ({ ...previous, [field]: value }));
        setFieldErrors((previous) => ({ ...previous, [field]: '' }));
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const errors: Record<string, string> = {};
        const title = values.title.trim();
        const subdomain = values.subdomain.trim();

        if (!title) {
            errors.title = t('network.network.form.errors.titleRequired', 'Title is required.');
        }

        if (!subdomain) {
            errors.subdomain = t('network.network.form.errors.subdomainRequired', 'Subdomain is required.');
        } else if (subdomain.length > 32) {
            errors.subdomain = t('network.network.form.errors.subdomainMax', 'Subdomain must be at most {{max}} characters.').replace('{{max}}', '32');
        } else if (!SUBDOMAIN_PATTERN.test(subdomain)) {
            errors.subdomain = t('network.network.form.errors.subdomainInvalid', 'Subdomain may only contain letters, numbers, dashes and underscores.');
        }

        setFieldErrors(errors);

        if (Object.keys(errors).length > 0) {
            return;
        }

        onSubmit({ title, subdomain, description: values.description.trim(), status: values.status });
    };

    return (
        <Modal open title={t('network.network.form.createTitle', 'Create website')} onClose={onClose}>
            <form onSubmit={handleSubmit} className="space-y-4">
                {error && <ErrorAlert message={error} />}

                <Input
                    label={t('network.network.form.title', 'Title')}
                    placeholder={t('network.network.form.titlePlaceholder', 'My Website')}
                    value={values.title}
                    onChange={(event) => setField('title', event.target.value)}
                    error={fieldErrors.title}
                />

                <div>
                    <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                        {t('network.network.form.subdomain', 'Subdomain')}
                    </label>
                    <div
                        className={`flex items-stretch overflow-hidden rounded-xl border bg-slate-50/80 transition-all focus-within:ring-2 dark:bg-slate-900/60 ${
                            fieldErrors.subdomain
                                ? 'border-rose-500 focus-within:ring-rose-500'
                                : 'border-slate-200 focus-within:border-indigo-500 focus-within:ring-indigo-500/20 dark:border-white/[0.08]'
                        }`}
                    >
                        <input
                            value={values.subdomain}
                            onChange={(event) => setField('subdomain', event.target.value)}
                            placeholder={t('network.network.form.subdomainPlaceholder', 'my-site')}
                            autoComplete="off"
                            className="min-w-0 flex-1 bg-transparent px-3.5 py-2.5 text-sm text-slate-900 focus:outline-none dark:text-white"
                        />
                        {networkDomain && (
                            <span className="flex select-none items-center whitespace-nowrap border-l border-slate-200 px-3 text-sm text-slate-500 dark:border-white/[0.08] dark:text-slate-400">
                                .{networkDomain}
                            </span>
                        )}
                    </div>
                    {fieldErrors.subdomain ? (
                        <p className="mt-1 text-xs text-rose-500">{fieldErrors.subdomain}</p>
                    ) : (
                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {t('network.network.form.subdomainHint', 'Letters, numbers, dashes and underscores only.')}
                        </p>
                    )}
                </div>

                <div>
                    <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                        {t('network.network.form.status', 'Status')}
                    </label>
                    <select
                        value={values.status}
                        onChange={(event) => setField('status', event.target.value as WebsiteStatus)}
                        className="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3.5 py-2.5 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-white/[0.08] dark:bg-slate-900/60 dark:text-white"
                    >
                        {WEBSITE_STATUSES.map((status) => (
                            <option key={status} value={status}>
                                {t(`network.network.status.${status}`, status)}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                        {t('network.network.form.description', 'Description')}
                    </label>
                    <textarea
                        rows={3}
                        value={values.description}
                        onChange={(event) => setField('description', event.target.value)}
                        placeholder={t('network.network.form.descriptionPlaceholder', 'Short description (optional)')}
                        className="w-full resize-none rounded-xl border border-slate-200 bg-slate-50/80 px-3.5 py-2.5 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-white/[0.08] dark:bg-slate-900/60 dark:text-white"
                    />
                </div>

                <div className="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="outline" onClick={onClose} disabled={isSubmitting}>
                        {t('network.network.form.cancel', 'Cancel')}
                    </Button>
                    <Button type="submit" isLoading={isSubmitting}>
                        {t('network.network.form.create', 'Create')}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
