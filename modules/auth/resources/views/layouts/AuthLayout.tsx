import type { ReactNode } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import type { SharedProps } from '@/types';

interface AuthLayoutProps {
    title?: string;
    children: ReactNode;
}

export default function AuthLayout({ title, children }: AuthLayoutProps) {
    const { flash } = usePage<SharedProps>().props;

    return (
        <div className="flex min-h-screen flex-col justify-center bg-slate-50 px-4 py-12 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
            <Head title={title} />

            <div className="mx-auto w-full max-w-md">
                <Link href="/" className="mb-6 flex items-center justify-center gap-2">
                    <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 to-purple-500 text-white">
                        <Sparkles className="h-5 w-5" />
                    </span>
                    <span className="text-lg font-extrabold tracking-tight">Admin</span>
                </Link>

                {title && (
                    <h1 className="mb-6 text-center text-2xl font-extrabold text-slate-900 dark:text-white">
                        {title}
                    </h1>
                )}

                <div className="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xl sm:p-8 dark:border-white/[0.08] dark:bg-slate-900">
                    {flash.success && (
                        <div className="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs text-emerald-700 dark:text-emerald-400">
                            {flash.success}
                        </div>
                    )}

                    {flash.error && (
                        <div className="mb-4 rounded-xl border border-rose-500/20 bg-rose-500/10 p-3 text-xs text-rose-600 dark:text-rose-400">
                            {flash.error}
                        </div>
                    )}

                    {children}
                </div>
            </div>
        </div>
    );
}
