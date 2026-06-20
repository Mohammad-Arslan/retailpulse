import AdminFormField from '@/Components/common/AdminFormField';
import Select from '@/Components/ui/select';
import { useMemo } from 'react';

export default function CountScopeFields({
    data,
    setData,
    errors,
    zonesByWarehouse,
    categories,
    t,
}) {
    const scopeTypeOptions = useMemo(
        () => [
            { value: 'full', label: t('pages.countSessions.scope.full') },
            { value: 'zone', label: t('pages.countSessions.scope.zone') },
            { value: 'category', label: t('pages.countSessions.scope.category') },
        ],
        [t],
    );

    const zoneOptions = useMemo(() => {
        const zones =
            zonesByWarehouse?.[data.warehouse_id] ?? zonesByWarehouse?.[String(data.warehouse_id)] ?? [];

        return zones.map((zone) => ({
            value: String(zone.id),
            label: `${zone.name} (${zone.code})`,
        }));
    }, [zonesByWarehouse, data.warehouse_id]);

    const categoryOptions = useMemo(
        () =>
            (categories ?? []).map((category) => ({
                value: String(category.id),
                label: category.name,
            })),
        [categories],
    );

    return (
        <>
            <AdminFormField label={t('pages.countSessions.fields.scope')} error={errors.scope_type}>
                <Select
                    options={scopeTypeOptions}
                    value={data.scope_type}
                    onChange={(value) => {
                        setData('scope_type', value ?? 'full');
                        setData('scope_id', '');
                    }}
                    isSearchable={false}
                />
            </AdminFormField>
            {data.scope_type === 'zone' && (
                <AdminFormField label={t('pages.countSessions.scope.zone')} error={errors.scope_id}>
                    <Select
                        options={zoneOptions}
                        value={data.scope_id ? String(data.scope_id) : ''}
                        onChange={(value) => setData('scope_id', value ?? '')}
                        placeholder={t('pages.countSessions.selectScope')}
                        required
                    />
                </AdminFormField>
            )}
            {data.scope_type === 'category' && (
                <AdminFormField label={t('pages.countSessions.scope.category')} error={errors.scope_id}>
                    <Select
                        options={categoryOptions}
                        value={data.scope_id ? String(data.scope_id) : ''}
                        onChange={(value) => setData('scope_id', value ?? '')}
                        placeholder={t('pages.countSessions.selectScope')}
                        required
                    />
                </AdminFormField>
            )}
        </>
    );
}
