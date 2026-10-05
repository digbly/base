import { useMemo, useState, type FormEvent } from 'react';
import Button from '@/components/ui/Button';
import ErrorAlert from '@/components/ui/ErrorAlert';
import Input from '@/components/ui/Input';
import Modal from '@/components/ui/Modal';
import { useTranslation } from '@/hooks/useTranslation';
import { WEBSITE_STATUSES, type AdminUser, type Website, type WebsiteStatus } from '../../types';

const SUBDOMAIN_PATTERN = /^[a-zA-Z0-9_-]+$/;

export interface WebsiteFormValues {
    title: string;
    subdomain: string;
    domain: string;
    status: WebsiteStatus;
    user_id: string;
    description: string;
}

interface WebsiteFormModalProps {
    website?: Website | null;
    owners: AdminUser[];
    networkDomain: string | null;
    isSubmitting: boolean;
    error: string | null;
    onSubmit: (values: WebsiteFormValues) => void;
    onClose: () => void;
}

export default function WebsiteFormModal({
    website = null,
    owners,
    networkDomain,
    isSubmitting,
    error,
    onSubmit,
    onClose,
}: WebsiteFormModalProps) {
    const { t } = useTranslation();
    const isEdit = Boolean(website);

    // The owner list is capped, so keep the current owner selectable when editing.
    const ownerOptions = useMemo(() => {
        const currentOwner = website?.owner;

        if (currentOwner && !owners.some((owner) => String(owner.id) === String(currentOwner.id))) {
            return [currentOwner, ...owners];
        }

        return owners;
    }, [owners, website]);

    const [values, setValues] = useState<WebsiteFormValues>(() => ({
        title: website?.title ?? '',
        subdomain: website?.subdomain ?? '',
        domain: website?.domain ?? '',
        status: website?.status ?? 'active',
        user_id: String(website?.user_id ?? owners[0]?.id ?? ''),
        description: website?.description ?? '',
    }));
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
        const domain = values.domain.trim();

        if (!title) {
            errors.title = t('network.networkAdmin.websiteForm.errors.titleRequired', 'Title is required.');
        }

        if (!subdomain) {
            errors.subdomain = t('network.networkAdmin.websiteForm.errors.subdomainRequired', 'Subdomain is required.');
        } else if (subdomain.length > 32) {
            errors.subdomain = t('network.networkAdmin.websiteForm.errors.subdomainMax', 'Subdomain must be at most {{max}} characters.').replace('{{max}}', '32');
        } else if (!SUBDOMAIN_PATTERN.test(subdomain)) {
            errors.subdomain = t('network.networkAdmin.websiteForm.errors.subdomainInvalid', 'Subdomain may only contain letters, numbers, dashes and underscores.');
        }

        if (domain.length > 64) {
            errors.domain = t('network.networkAdmin.websiteForm.errors.domainMax', 'Domain must be at most {{max}} characters.').replace('{{max}}', '64');
        }

        if (!values.user_id) {
            errors.user_id = t('network.networkAdmin.websiteForm.errors.ownerRequired', 'Please select an owner.');
        }

        setFieldErrors(errors);

        if (Object.keys(errors).length > 0) {
            return;
        }

        onSubmit({ title, subdomain, domain, status: values.status, user_id: values.user_id, description: values.description.trim() });
    };

    const selectClassName =
        'w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3.5 py-2.5 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-white/[0.08] dark:bg-slate-900/60 dark:text-white';

    return (
        <Modal
            open
            title={
                isEdit
                    ? t('network.networkAdmin.websiteForm.editTitle', 'Edit website')
                    : t('network.networkAdmin.websiteForm.createTitle', 'Create website')
            }
            onClose={onClose}
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                {error && <ErrorAlert message={error} />}

                <Input
                    label={t('network.networkAdmin.websiteForm.title', 'Title')}
                    placeholder={t('network.networkAdmin.websiteForm.titlePlaceholder', 'My Website')}
                    value={values.title}
                    onChange={(event) => setField('title', event.target.value)}
                    error={fieldErrors.title}
                />

                <div>
                    <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                        {t('network.networkAdmin.websiteForm.subdomain', 'Subdomain')}
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
                            placeholder={t('network.networkAdmin.websiteForm.subdomainPlaceholder', 'my-site')}
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
                            {t('network.networkAdmin.websiteForm.subdomainHint', 'Letters, numbers, dashes and underscores only.')}
                        </p>
                    )}
                </div>

                <Input
                    label={t('network.networkAdmin.websiteForm.domain', 'Custom domain')}
                    placeholder={t('network.networkAdmin.websiteForm.domainPlaceholder', 'my-site.com')}
                    value={values.domain}
                    onChange={(event) => setField('domain', event.target.value)}
                    error={fieldErrors.domain}
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                            {t('network.networkAdmin.websiteForm.status', 'Status')}
                        </label>
                        <select
                            value={values.status}
                            onChange={(event) => setField('status', event.target.value as WebsiteStatus)}
                            className={selectClassName}
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
                            {t('network.networkAdmin.websiteForm.owner', 'Owner')}
                        </label>
                        <select
                            value={values.user_id}
                            onChange={(event) => setField('user_id', event.target.value)}
                            className={selectClassName}
                        >
                            <option value="">
                                {t('network.networkAdmin.websiteForm.ownerPlaceholder', 'Select an owner')}
                            </option>
                            {ownerOptions.map((owner) => (
                                <option key={owner.id} value={String(owner.id)}>
                                    {owner.name} ({owner.email})
                                </option>
                            ))}
                        </select>
                        {fieldErrors.user_id && <p className="mt-1 text-xs text-rose-500">{fieldErrors.user_id}</p>}
                    </div>
                </div>

                <div>
                    <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                        {t('network.networkAdmin.websiteForm.description', 'Description')}
                    </label>
                    <textarea
                        rows={3}
                        value={values.description}
                        onChange={(event) => setField('description', event.target.value)}
                        placeholder={t('network.networkAdmin.websiteForm.descriptionPlaceholder', 'Short description (optional)')}
                        className="w-full resize-none rounded-xl border border-slate-200 bg-slate-50/80 px-3.5 py-2.5 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-white/[0.08] dark:bg-slate-900/60 dark:text-white"
                    />
                </div>

                <div className="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="outline" onClick={onClose} disabled={isSubmitting}>
                        {t('network.networkAdmin.websiteForm.cancel', 'Cancel')}
                    </Button>
                    <Button type="submit" isLoading={isSubmitting}>
                        {isEdit
                            ? t('network.networkAdmin.websiteForm.save', 'Save changes')
                            : t('network.networkAdmin.websiteForm.create', 'Create')}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
