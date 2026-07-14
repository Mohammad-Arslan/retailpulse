import Checkbox from '@/Components/Checkbox';
import InputLabel from '@/Components/InputLabel';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function groupLabel(group, t) {
    const key = `pages.roles.permissionGroups.${group}`;
    const translated = t(key, { defaultValue: '' });
    if (translated && translated !== key) {
        return translated;
    }
    return group
        .split(/[-_]/)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function matchesPermission(permission, groupKey, label, query) {
    if (!query) {
        return true;
    }

    return [permission.display_name, permission.name, permission.description, groupKey, label]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
        .includes(query);
}

export default function PermissionCheckboxes({
    permissionGroups,
    selected = [],
    onChange,
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');
    const query = search.trim().toLowerCase();

    const toggle = (name) => {
        if (selected.includes(name)) {
            onChange(selected.filter((p) => p !== name));
        } else {
            onChange([...selected, name]);
        }
    };

    const filteredEntries = useMemo(() => {
        return Object.entries(permissionGroups)
            .map(([group, permissions]) => {
                const label = groupLabel(group, t);
                return {
                    group,
                    label,
                    permissions: permissions.filter((permission) =>
                        matchesPermission(permission, group, label, query),
                    ),
                };
            })
            .filter((entry) => entry.permissions.length > 0);
    }, [permissionGroups, query, t]);

    return (
        <div className="space-y-4">
            <div className="rp-search-inset">
                <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                <input
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={t('pages.roles.searchPermissionsPlaceholder')}
                    className="rp-search-input"
                    aria-label={t('common.search')}
                />
            </div>

            {filteredEntries.length === 0 ? (
                <p className="py-6 text-center text-sm text-rp-text-muted">
                    {t('pages.permissions.emptySearch')}
                </p>
            ) : (
                <div className="space-y-6">
                    {filteredEntries.map(({ group, label, permissions }) => (
                        <div key={group} className="rounded-md border border-rp-border p-4">
                            <h4 className="mb-3 text-sm font-semibold text-rp-text">{label}</h4>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {permissions.map((permission) => (
                                    <label
                                        key={permission.name}
                                        className="flex items-start gap-2 rounded-lg px-1 py-1 hover:bg-rp-surface-inset/60"
                                    >
                                        <Checkbox
                                            checked={selected.includes(permission.name)}
                                            onChange={() => toggle(permission.name)}
                                            className="mt-0.5"
                                        />
                                        <span className="min-w-0">
                                            <InputLabel
                                                value={permission.display_name || permission.name}
                                                className="!mb-0 font-medium"
                                            />
                                            <span className="block font-mono text-[11px] text-rp-text-muted">
                                                {permission.name}
                                            </span>
                                            {permission.description && (
                                                <span className="mt-0.5 block text-xs text-rp-text-secondary">
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
            )}
        </div>
    );
}
