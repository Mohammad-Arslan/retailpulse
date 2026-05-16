import { Link } from '@inertiajs/react';
import { Building2, ChevronRight } from 'lucide-react';

export default function DashboardBranchesSnapshot({ branches }) {
    if (!branches?.length) {
        return (
            <div className="rp-card">
                <div className="mb-3 flex items-center justify-between gap-3">
                    <h2 className="rp-section-title inline-flex items-center gap-2">
                        <Building2 className="h-4 w-4 text-rp-text-muted" />
                        Branches
                    </h2>
                    <Link
                        href={route('admin.branches.index')}
                        className="text-sm font-medium text-teal-600 hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-300"
                    >
                        Manage
                    </Link>
                </div>
                <p className="text-sm text-rp-text-muted">
                    No active branches yet. Create a branch to start tracking warehouses and inventory by location.
                </p>
            </div>
        );
    }

    return (
        <div className="rp-card">
            <div className="mb-4 flex items-center justify-between gap-3">
                <h2 className="rp-section-title inline-flex items-center gap-2">
                    <Building2 className="h-4 w-4 text-rp-text-muted" />
                    Branches
                </h2>
                <Link
                    href={route('admin.branches.index')}
                    className="text-sm font-medium text-teal-600 hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-300"
                >
                    View all
                </Link>
            </div>
            <ul className="divide-y divide-rp-border/60">
                {branches.map((b) => (
                    <li key={b.id}>
                        <Link
                            href={route('admin.branches.edit', b.id)}
                            className="group flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <div className="min-w-0">
                                <div className="truncate font-medium text-rp-text">{b.name}</div>
                                <div className="mt-0.5 text-xs text-rp-text-muted">
                                    Code {b.code}
                                    <span className="mx-1.5 text-rp-border">·</span>
                                    {b.warehouses_count} warehouse{b.warehouses_count === 1 ? '' : 's'}
                                    <span className="mx-1.5 text-rp-border">·</span>
                                    {b.users_count} user{b.users_count === 1 ? '' : 's'}
                                </div>
                            </div>
                            <ChevronRight className="h-4 w-4 shrink-0 text-rp-text-muted opacity-0 transition group-hover:opacity-100" />
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}
