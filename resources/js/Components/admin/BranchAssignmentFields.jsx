import AdminFormField from '@/Components/common/AdminFormField';

export default function BranchAssignmentFields({
    availableBranches,
    assignments,
    onChange,
    error,
}) {
    if (!availableBranches?.length) {
        return null;
    }

    const isAssigned = (branchId) =>
        assignments.some((a) => a.branch_id === branchId);

    const toggleBranch = (branchId) => {
        if (isAssigned(branchId)) {
            onChange(assignments.filter((a) => a.branch_id !== branchId));
            return;
        }

        onChange([
            ...assignments,
            {
                branch_id: branchId,
                is_primary: assignments.length === 0,
            },
        ]);
    };

    const setPrimary = (branchId) => {
        onChange(
            assignments.map((a) => ({
                ...a,
                is_primary: a.branch_id === branchId,
            })),
        );
    };

    return (
        <AdminFormField label="Branches" error={error}>
            <div className="space-y-2">
                {availableBranches.map((branch) => {
                    const assigned = isAssigned(branch.id);
                    const primary = assignments.find(
                        (a) => a.branch_id === branch.id,
                    )?.is_primary;

                    return (
                        <div
                            key={branch.id}
                            className="flex flex-wrap items-center gap-3 rounded-lg border border-rp-border px-3 py-2"
                        >
                            <label className="rp-checkbox-label flex-1">
                                <input
                                    type="checkbox"
                                    checked={assigned}
                                    onChange={() => toggleBranch(branch.id)}
                                    className="accent-teal-500"
                                />
                                <span>
                                    {branch.name}
                                    <span className="ml-1 text-xs text-rp-text-muted">
                                        ({branch.code})
                                    </span>
                                </span>
                            </label>
                            {assigned && (
                                <label className="rp-checkbox-label text-xs">
                                    <input
                                        type="radio"
                                        name="primary_branch"
                                        checked={primary}
                                        onChange={() => setPrimary(branch.id)}
                                        className="accent-teal-500"
                                    />
                                    Primary
                                </label>
                            )}
                        </div>
                    );
                })}
            </div>
        </AdminFormField>
    );
}
