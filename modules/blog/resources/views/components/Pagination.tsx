import { ChevronLeft, ChevronRight } from 'lucide-react';
import Button from '@/components/ui/Button';
import { useTranslation } from '@/hooks/useTranslation';
import type { PaginationMeta } from '../types';

interface PaginationProps {
    meta?: PaginationMeta | null;
    onPageChange: (page: number) => void;
}

export default function Pagination({ meta, onPageChange }: PaginationProps) {
    const { t } = useTranslation();

    if (!meta) {
        return null;
    }

    const { current_page: currentPage, last_page: lastPage, from, to, total } = meta;

    return (
        <div className="flex flex-col gap-3 border-t border-slate-100 px-6 py-4 dark:border-white/[0.06] sm:flex-row sm:items-center sm:justify-between">
            <p className="text-xs text-slate-500 dark:text-slate-400">
                {t('blog.pagination.summary', 'Showing {{from}}-{{to}} of {{total}} items')
                    .replace('{{from}}', String(from ?? 0))
                    .replace('{{to}}', String(to ?? 0))
                    .replace('{{total}}', String(total))}
            </p>

            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={currentPage <= 1}
                    onClick={() => onPageChange(currentPage - 1)}
                    leftIcon={<ChevronLeft className="h-3.5 w-3.5" />}
                >
                    {t('blog.pagination.previous', 'Previous')}
                </Button>

                <span className="px-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                    {t('blog.pagination.page', 'Page {{current}} / {{total}}')
                        .replace('{{current}}', String(currentPage))
                        .replace('{{total}}', String(lastPage))}
                </span>

                <Button
                    variant="outline"
                    size="sm"
                    disabled={currentPage >= lastPage}
                    onClick={() => onPageChange(currentPage + 1)}
                    rightIcon={<ChevronRight className="h-3.5 w-3.5" />}
                >
                    {t('blog.pagination.next', 'Next')}
                </Button>
            </div>
        </div>
    );
}
