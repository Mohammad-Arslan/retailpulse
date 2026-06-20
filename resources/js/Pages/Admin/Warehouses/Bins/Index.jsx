import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function BinsIndex({ warehouse, zones, bins }) {
    const can = useCan();
    const { t } = useTranslation();
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
                    {can('inventory.manage-bins') && (
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
                            <li
                                key={zone.id}
                                className="flex items-center justify-between rounded-lg border border-rp-border px-3 py-2 text-sm"
                            >
                                <span>
                                    {zone.name}{' '}
                                    <span className="font-mono text-rp-text-muted">({zone.code})</span>
                                </span>
                                {!zone.is_active && (
                                    <span className="text-xs text-rp-text-muted">{t('pages.warehouses.inactive')}</span>
                                )}
                            </li>
                        ))}
                        {zones.length === 0 && (
                            <p className="text-sm text-rp-text-muted">{t('pages.bins.noZones')}</p>
                        )}
                    </ul>
                </FormCard>

                <FormCard title={t('pages.bins.binLocations')}>
                    {can('inventory.manage-bins') && (
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
                                <select
                                    className="rp-form-input w-full"
                                    value={binForm.data.warehouse_zone_id}
                                    onChange={(e) => binForm.setData('warehouse_zone_id', e.target.value)}
                                >
                                    <option value="">{t('pages.bins.noZone')}</option>
                                    {zones.map((z) => (
                                        <option key={z.id} value={z.id}>
                                            {z.name}
                                        </option>
                                    ))}
                                </select>
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
                                </tr>
                            </thead>
                            <tbody>
                                {bins.map((bin) => (
                                    <tr key={bin.id} className="border-b border-rp-border/50">
                                        <td className="py-2 font-mono">{bin.bin_code}</td>
                                        <td className="py-2">{bin.zone_name ?? '—'}</td>
                                        <td className="py-2 text-rp-text-muted">
                                            {[bin.aisle, bin.shelf].filter(Boolean).join('-') || '—'}
                                        </td>
                                    </tr>
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
