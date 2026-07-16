import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Network, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function OrgNode({ node, depth = 0 }) {
    const [expanded, setExpanded] = useState(depth < 2);
    const hasChildren = node.children?.length > 0;

    return (
        <div className="ml-0">
            <div
                className="flex items-start gap-2 rounded-lg border border-rp-border bg-rp-surface px-3 py-2"
                style={{ marginLeft: depth * 20 }}
            >
                {hasChildren ? (
                    <button
                        type="button"
                        onClick={() => setExpanded((v) => !v)}
                        className="mt-0.5 text-rp-text-muted hover:text-rp-text"
                        aria-label={expanded ? 'Collapse' : 'Expand'}
                    >
                        {expanded ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                    </button>
                ) : (
                    <span className="w-4" />
                )}
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                    <Users className="h-4 w-4" />
                </span>
                <div className="min-w-0">
                    <Link
                        href={route('admin.hr.employees.show', node.id)}
                        className="text-sm font-semibold text-teal-600 hover:underline"
                    >
                        {node.name}
                    </Link>
                    <div className="text-xs text-rp-text-muted">{node.employee_code}</div>
                    {(node.designation || node.department) && (
                        <div className="text-xs text-rp-text-muted">
                            {[node.designation, node.department].filter(Boolean).join(' · ')}
                        </div>
                    )}
                </div>
            </div>
            {expanded &&
                hasChildren &&
                node.children.map((child) => <OrgNode key={child.id} node={child} depth={depth + 1} />)}
        </div>
    );
}

function OrgChart({ tree = [], filters = {}, legalEntities = [], employees = [] }) {
    const { t } = useTranslation();

    const entityOptions = useMemo(
        () => [
            { value: '', label: t('pages.hrEmployees.allEntities') },
            ...legalEntities.map((e) => ({ value: String(e.id), label: e.legal_name })),
        ],
        [legalEntities, t],
    );

    const employeeOptions = useMemo(
        () => [
            { value: '', label: t('pages.orgChart.allRoots') },
            ...employees.map((e) => ({ value: String(e.id), label: e.label })),
        ],
        [employees, t],
    );

    const applyFilters = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.hr.org-chart.index'), Object.fromEntries(form), { preserveState: true });
    };

    return (
        <>
            <Head title={t('pages.orgChart.indexTitle')} />
            <PageHeader title={t('pages.orgChart.indexTitle')} description={t('pages.orgChart.indexDescription')}>
                <span className="inline-flex items-center gap-2 text-sm text-rp-text-muted">
                    <Network className="h-4 w-4" />
                    {t('pages.orgChart.hierarchyView')}
                </span>
            </PageHeader>

            <form onSubmit={applyFilters} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <Select
                    name="legal_entity_id"
                    defaultValue={filters.legal_entity_id ? String(filters.legal_entity_id) : ''}
                    className="w-auto min-w-[14rem]"
                    options={entityOptions}
                />
                <Select
                    name="root_employee_id"
                    defaultValue={filters.root_employee_id ? String(filters.root_employee_id) : ''}
                    className="w-auto min-w-[14rem]"
                    options={employeeOptions}
                />
                <Button type="submit" variant="outline">
                    {t('common.apply')}
                </Button>
            </form>

            {tree.length === 0 ? (
                <div className="rounded-lg border border-dashed border-rp-border p-8 text-center text-sm text-rp-text-muted">
                    {t('pages.orgChart.empty')}
                </div>
            ) : (
                <div className="space-y-2">
                    {tree.map((node) => (
                        <OrgNode key={node.id} node={node} />
                    ))}
                </div>
            )}
        </>
    );
}

export default withAdminLayout(OrgChart);
