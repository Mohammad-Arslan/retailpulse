import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function buildZoneOptions(zones, noZoneLabel) {
    return [
        { value: '', label: noZoneLabel },
        ...zones.map((zone) => ({
            value: String(zone.id),
            label: zone.name,
        })),
    ];
}

function ZoneRow({ warehouse, zone, canEdit, t }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        name: zone.name,
        code: zone.code,
        is_active: zone.is_active,
    });

    const save = (e) => {
        e.preventDefault();
        form.put(route('admin.warehouses.zones.update', [warehouse.id, zone.id]), {
            onSuccess: () => setEditing(false),
        });
    };

    if (editing) {
        return (
            <li className="rounded-lg border border-rp-border px-3 py-3 text-sm">
                <form onSubmit={save} className="space-y-2">
                    <input
                        className="rp-form-input w-full"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        required
                    />
                    <input
                        className="rp-form-input w-full font-mono uppercase"
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        required
                    />
                    <label className="flex items-center gap-2 text-xs">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                        />
                        {t('pages.bins.fields.active')}
                    </label>
                    <div className="flex gap-2">
                        <button type="submit" disabled={form.processing} className="rp-btn-primary text-xs">
                            {t('common.save')}
                        </button>
                        <button type="button" onClick={() => setEditing(false)} className="rp-btn-outline text-xs">
                            {t('confirm.cancel')}
                        </button>
                    </div>
                </form>
            </li>
        );
    }

    return (
        <li className="flex items-center justify-between rounded-lg border border-rp-border px-3 py-2 text-sm">
            <span>
                {zone.name} <span className="font-mono text-rp-text-muted">({zone.code})</span>
            </span>
            <div className="flex items-center gap-2">
                {!zone.is_active && (
                    <span className="text-xs text-rp-text-muted">{t('pages.warehouses.inactive')}</span>
                )}
                {canEdit && (
                    <button type="button" onClick={() => setEditing(true)} className="rp-btn-outline text-xs">
                        {t('pages.bins.editZone')}
                    </button>
                )}
            </div>
        </li>
    );
}

function BinRow({ warehouse, bin, zones, canEdit, t }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        warehouse_zone_id: bin.warehouse_zone_id ?? '',
        aisle: bin.aisle ?? '',
        shelf: bin.shelf ?? '',
        bin_code: bin.bin_code,
        capacity_limit: bin.capacity_limit ?? '',
        is_active: bin.is_active,
    });

    const zoneOptions = useMemo(
        () => buildZoneOptions(zones, t('pages.bins.noZone')),
        [zones, t],
    );

    const save = (e) => {
        e.preventDefault();
        form.put(route('admin.warehouses.bins.update', [warehouse.id, bin.id]), {
            onSuccess: () => setEditing(false),
        });
    };

    if (editing) {
        return (
            <tr className="border-b border-rp-border/50 bg-rp-surface-muted/40">
                <td colSpan={4} className="px-3 py-3">
                    <form onSubmit={save} className="grid gap-2 sm:grid-cols-2">
                        <Select
                            options={zoneOptions}
                            value={form.data.warehouse_zone_id ? String(form.data.warehouse_zone_id) : ''}
                            onChange={(value) => form.setData('warehouse_zone_id', value ?? '')}
                            placeholder={t('pages.bins.noZone')}
                        />
                        <input
                            className="rp-form-input w-full font-mono uppercase"
                            value={form.data.bin_code}
                            onChange={(e) => form.setData('bin_code', e.target.value)}
                            required
                        />
                        <input
                            className="rp-form-input w-full"
                            placeholder={t('pages.bins.fields.aisle')}
                            value={form.data.aisle}
                            onChange={(e) => form.setData('aisle', e.target.value)}
                        />
                        <input
                            className="rp-form-input w-full"
                            placeholder={t('pages.bins.fields.shelf')}
                            value={form.data.shelf}
                            onChange={(e) => form.setData('shelf', e.target.value)}
                        />
                        <input
                            type="number"
                            min="0"
                            className="rp-form-input w-full"
                            placeholder={t('pages.bins.fields.capacityLimit')}
                            value={form.data.capacity_limit}
                            onChange={(e) => form.setData('capacity_limit', e.target.value)}
                        />
                        <label className="flex items-center gap-2 text-xs">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(e) => form.setData('is_active', e.target.checked)}
                            />
                            {t('pages.bins.fields.active')}
                        </label>
                        <div className="flex gap-2 sm:col-span-2">
                            <button type="submit" disabled={form.processing} className="rp-btn-primary text-xs">
                                {t('common.save')}
                            </button>
                            <button type="button" onClick={() => setEditing(false)} className="rp-btn-outline text-xs">
                                {t('confirm.cancel')}
                            </button>
                        </div>
                    </form>
                </td>
            </tr>
        );
    }

    return (
        <tr className="border-b border-rp-border/50">
            <td className="py-2 font-mono">
                {bin.bin_code}
                {!bin.is_active && (
                    <span className="ml-2 text-xs text-rp-text-muted">{t('pages.warehouses.inactive')}</span>
                )}
            </td>
            <td className="py-2">{bin.zone_name ?? '—'}</td>
            <td className="py-2 text-rp-text-muted">
                {[bin.aisle, bin.shelf].filter(Boolean).join('-') || '—'}
            </td>
            <td className="py-2 text-right">
                {canEdit && (
                    <button type="button" onClick={() => setEditing(true)} className="rp-btn-outline text-xs">
                        {t('pages.bins.editBin')}
                    </button>
                )}
            </td>
        </tr>
    );
}

export default function BinsIndex({ warehouse, zones, bins }) {
    const can = useCan();
    const { t } = useTranslation();
    const canEdit = can('inventory.manage-bins');
    const [showZoneForm, setShowZoneForm] = useState(false);
    const [showBinForm, setShowBinForm] = useState(false);

    const zoneForm = useForm({ name: '', code: '' });
    const binForm = useForm({
        warehouse_zone_id: '',
        zone: '',
        aisle: '',
        shelf: '',
        bin_code: '',
        capacity_limit: '',
    });

    const zoneOptions = useMemo(
        () => buildZoneOptions(zones, t('pages.bins.noZone')),
        [zones, t],
    );

    const submitZone = (e) => {
        e.preventDefault();
        zoneForm.post(route('admin.warehouses.zones.store', warehouse.id), {
            onSuccess: () => {
                zoneForm.reset();
                setShowZoneForm(false);
            },
        });
    };

    const submitBin = (e) => {
        e.preventDefault();
        binForm.post(route('admin.warehouses.bins.store', warehouse.id), {
            onSuccess: () => {
                binForm.reset();
                setShowBinForm(false);
            },
        });
    };

    return (
        <AdminLayout>
            <Head title={t('pages.bins.title', { warehouse: warehouse.name })} />
            <PageHeader
                title={t('pages.bins.title', { warehouse: warehouse.name })}
                description={warehouse.branch_name}
            >
                <Link href={route('admin.warehouses.edit', warehouse.id)} className="rp-btn-outline">
                    {t('pages.bins.backToWarehouse')}
                </Link>
            </PageHeader>

            <div className="grid gap-6 lg:grid-cols-2">
                <FormCard title={t('pages.bins.zones')}>
                    {canEdit && (
                        <button
                            type="button"
                            onClick={() => setShowZoneForm((v) => !v)}
                            className="rp-btn-outline mb-4 text-sm"
                        >
                            {showZoneForm ? t('confirm.cancel') : t('pages.bins.addZone')}
                        </button>
                    )}
                    {showZoneForm && (
                        <form onSubmit={submitZone} className="mb-4 space-y-3 border-b border-rp-border pb-4">
                            <AdminFormField label={t('pages.bins.fields.name')} error={zoneForm.errors.name}>
                                <input
                                    className="rp-form-input w-full"
                                    value={zoneForm.data.name}
                                    onChange={(e) => zoneForm.setData('name', e.target.value)}
                                    required
                                />
                            </AdminFormField>
                            <AdminFormField label={t('pages.bins.fields.code')} error={zoneForm.errors.code}>
                                <input
                                    className="rp-form-input w-full font-mono uppercase"
                                    value={zoneForm.data.code}
                                    onChange={(e) => zoneForm.setData('code', e.target.value)}
                                    required
                                />
                            </AdminFormField>
                            <button type="submit" disabled={zoneForm.processing} className="rp-btn-primary text-sm">
                                {t('pages.bins.saveZone')}
                            </button>
                        </form>
                    )}
                    <ul className="space-y-2">
                        {zones.map((zone) => (
                            <ZoneRow
                                key={zone.id}
                                warehouse={warehouse}
                                zone={zone}
                                canEdit={canEdit}
                                t={t}
                            />
                        ))}
                        {zones.length === 0 && (
                            <p className="text-sm text-rp-text-muted">{t('pages.bins.noZones')}</p>
                        )}
                    </ul>
                </FormCard>

                <FormCard title={t('pages.bins.binLocations')}>
                    {canEdit && (
                        <button
                            type="button"
                            onClick={() => setShowBinForm((v) => !v)}
                            className="rp-btn-outline mb-4 text-sm"
                        >
                            {showBinForm ? t('confirm.cancel') : t('pages.bins.addBin')}
                        </button>
                    )}
                    {showBinForm && (
                        <form onSubmit={submitBin} className="mb-4 space-y-3 border-b border-rp-border pb-4">
                            <AdminFormField label={t('pages.bins.fields.zone')} error={binForm.errors.warehouse_zone_id}>
                                <Select
                                    options={zoneOptions}
                                    value={binForm.data.warehouse_zone_id ? String(binForm.data.warehouse_zone_id) : ''}
                                    onChange={(value) => binForm.setData('warehouse_zone_id', value ?? '')}
                                    placeholder={t('pages.bins.noZone')}
                                />
                            </AdminFormField>
                            <div className="grid grid-cols-3 gap-2">
                                <AdminFormField label={t('pages.bins.fields.aisle')} error={binForm.errors.aisle}>
                                    <input
                                        className="rp-form-input w-full"
                                        value={binForm.data.aisle}
                                        onChange={(e) => binForm.setData('aisle', e.target.value)}
                                    />
                                </AdminFormField>
                                <AdminFormField label={t('pages.bins.fields.shelf')} error={binForm.errors.shelf}>
                                    <input
                                        className="rp-form-input w-full"
                                        value={binForm.data.shelf}
                                        onChange={(e) => binForm.setData('shelf', e.target.value)}
                                    />
                                </AdminFormField>
                                <AdminFormField label={t('pages.bins.fields.binCode')} error={binForm.errors.bin_code}>
                                    <input
                                        className="rp-form-input w-full font-mono uppercase"
                                        value={binForm.data.bin_code}
                                        onChange={(e) => binForm.setData('bin_code', e.target.value)}
                                        required
                                    />
                                </AdminFormField>
                            </div>
                            <button type="submit" disabled={binForm.processing} className="rp-btn-primary text-sm">
                                {t('pages.bins.saveBin')}
                            </button>
                        </form>
                    )}
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-rp-border text-left text-rp-text-muted">
                                    <th className="py-2">{t('pages.bins.fields.binCode')}</th>
                                    <th className="py-2">{t('pages.bins.fields.zone')}</th>
                                    <th className="py-2">{t('pages.bins.fields.location')}</th>
                                    <th className="py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {bins.map((bin) => (
                                    <BinRow
                                        key={bin.id}
                                        warehouse={warehouse}
                                        bin={bin}
                                        zones={zones}
                                        canEdit={canEdit}
                                        t={t}
                                    />
                                ))}
                            </tbody>
                        </table>
                        {bins.length === 0 && (
                            <p className="py-4 text-sm text-rp-text-muted">{t('pages.bins.noBins')}</p>
                        )}
                    </div>
                </FormCard>
            </div>
        </AdminLayout>
    );
}
