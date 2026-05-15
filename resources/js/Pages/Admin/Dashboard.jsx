import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';

export default function Dashboard({ stats }) {
    return (
        <AdminLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="grid gap-4 sm:grid-cols-3">
                <StatCard label="Users" value={stats.users} />
                <StatCard label="Roles" value={stats.roles} />
                <StatCard label="Permissions" value={stats.permissions} />
            </div>
        </AdminLayout>
    );
}

function StatCard({ label, value }) {
    return (
        <div className="overflow-hidden rounded-lg bg-white shadow">
            <div className="p-6">
                <p className="text-sm font-medium text-gray-500">{label}</p>
                <p className="mt-2 text-3xl font-semibold text-gray-900">
                    {value}
                </p>
            </div>
        </div>
    );
}
