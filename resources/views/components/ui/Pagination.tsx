import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationProps {
    links: PaginationLink[];
    lastPage: number;
    className?: string;
}

/** Strip the arrow entities Laravel ships around previous/next labels. */
const pageLabel = (label: string): string => label.replace(/&laquo;|&raquo;/g, '').trim();

/**
 * Compact pagination control driven by Laravel's `links` payload.
 */
export default function Pagination({ links, lastPage, className = '' }: PaginationProps) {
    if (lastPage <= 1 || links.length <= 3) {
        return null;
    }

    const go = (url: string) => router.get(url, {}, { preserveState: true, preserveScroll: true });

    return (
        <nav className={`mt-4 flex items-center justify-center gap-1 ${className}`} aria-label="Pagination">
            {links.map((link, index) => {
                const isPrevious = index === 0;
                const isNext = index === links.length - 1;
                const label = pageLabel(link.label);

                if (!link.url) {
                    return (
                        <span
                            key={index}
                            className="flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs text-slate-300 dark:text-slate-600"
                        >
                            {isPrevious ? <ChevronLeft className="h-4 w-4" /> : isNext ? <ChevronRight className="h-4 w-4" /> : label}
                        </span>
                    );
                }

                return (
                    <button
                        key={index}
                        type="button"
                        onClick={() => go(link.url as string)}
                        aria-current={link.active ? 'page' : undefined}
                        className={`flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs font-medium transition ${
                            link.active
                                ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-600/30'
                                : 'border border-slate-200 text-slate-600 hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-slate-800'
                        }`}
                    >
                        {isPrevious ? <ChevronLeft className="h-4 w-4" /> : isNext ? <ChevronRight className="h-4 w-4" /> : label}
                    </button>
                );
            })}
        </nav>
    );
}
