import AdminLayout from '../layouts/AdminLayout';

interface Stat {
    label: string;
    value: string | number;
}

interface DashboardProps {
    title: string;
    stats: Stat[];
}

export default function Dashboard({ title, stats = [] }: DashboardProps) {
    return (
        <AdminLayout title={title}>
            <h1 className="mb-6 text-2xl font-bold">{title}</h1>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {stats.map((stat) => (
                    <div
                        key={stat.label}
                        className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <p className="text-sm text-slate-500 dark:text-slate-400">{stat.label}</p>
                        <p className="mt-2 text-3xl font-bold">{stat.value}</p>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
