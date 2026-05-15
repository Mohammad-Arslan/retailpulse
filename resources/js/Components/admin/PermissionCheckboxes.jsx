import Checkbox from '@/Components/Checkbox';
import InputLabel from '@/Components/InputLabel';

export default function PermissionCheckboxes({
    permissionGroups,
    selected = [],
    onChange,
}) {
    const toggle = (name) => {
        if (selected.includes(name)) {
            onChange(selected.filter((p) => p !== name));
        } else {
            onChange([...selected, name]);
        }
    };

    return (
        <div className="space-y-6">
            {Object.entries(permissionGroups).map(([group, permissions]) => (
                <div key={group} className="rounded-md border border-gray-200 p-4">
                    <h4 className="mb-3 text-sm font-semibold uppercase text-gray-600">
                        {group}
                    </h4>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {permissions.map((permission) => (
                            <label
                                key={permission.name}
                                className="flex items-start gap-2"
                            >
                                <Checkbox
                                    checked={selected.includes(permission.name)}
                                    onChange={() => toggle(permission.name)}
                                />
                                <span>
                                    <InputLabel
                                        value={permission.name}
                                        className="!mb-0"
                                    />
                                    {permission.description && (
                                        <span className="block text-xs text-gray-500">
                                            {permission.description}
                                        </span>
                                    )}
                                </span>
                            </label>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
