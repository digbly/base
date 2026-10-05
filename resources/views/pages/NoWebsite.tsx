import { Head, router, usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

export default function NoWebsite() {
    const { auth } = usePage<SharedProps>().props;

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 text-center text-slate-700 dark:bg-slate-950 dark:text-slate-200">
            <Head title="No website" />

            <h1 className="text-2xl font-bold">No website available</h1>
            <p className="mt-2 max-w-md text-sm text-slate-500 dark:text-slate-400">
                Your account does not have access to any website yet.
            </p>

            <span className="mt-6 text-sm">{auth.user?.email}</span>

            <button
                type="button"
                onClick={() => router.post('/logout')}
                className="mt-4 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            >
                Logout
            </button>
        </div>
    );
}
