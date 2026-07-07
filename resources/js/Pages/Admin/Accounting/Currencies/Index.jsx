import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ currencies = [], exchangeRates = [], functionalCurrency = 'USD' }) {
    const { t } = useTranslation();
    const currencyForm = useForm({ code: '', name: '', symbol: '$', decimal_places: 2 });
    const rateForm = useForm({
        currency_id: currencies[0]?.id ? String(currencies[0].id) : '',
        rate_date: new Date().toISOString().slice(0, 10),
        rate: '',
        rate_type: 'spot',
    });

    const currencyOptions = useMemo(
        () => currencies.map((c) => ({ value: String(c.id), label: c.code })),
        [currencies],
    );

    const rateTypeOptions = useMemo(
        () => [
            { value: 'spot', label: t('pages.accounting.currencies.rateTypes.spot') },
            { value: 'average', label: t('pages.accounting.currencies.rateTypes.average') },
            { value: 'closing', label: t('pages.accounting.currencies.rateTypes.closing') },
            { value: 'custom', label: t('pages.accounting.currencies.rateTypes.custom') },
        ],
        [t],
    );

    const columns = useMemo(
        () => [
            { id: 'code', header: t('pages.accounting.currencies.columns.code'), cell: ({ row }) => row.original.code },
            { id: 'name', header: t('common.name'), cell: ({ row }) => row.original.name },
            { id: 'symbol', header: t('pages.accounting.currencies.columns.symbol'), cell: ({ row }) => row.original.symbol },
            { id: 'status', header: t('common.status'), cell: ({ row }) => row.original.status },
        ],
        [t],
    );

    const rateColumns = useMemo(
        () => [
            { id: 'currency', header: t('common.currency'), cell: ({ row }) => row.original.currency_code },
            { id: 'date', header: t('common.date'), cell: ({ row }) => row.original.rate_date },
            { id: 'type', header: t('pages.accounting.currencies.columns.rateType'), cell: ({ row }) => row.original.rate_type },
            { id: 'rate', header: t('pages.accounting.currencies.columns.rate'), cell: ({ row }) => row.original.rate },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.accounting.currencies.title')} />
            <PageHeader title={t('pages.accounting.currencies.title')} description={t('pages.accounting.currencies.description', { currency: functionalCurrency })} />
            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <DataTable columns={columns} data={currencies} emptyMessage={t('pages.accounting.currencies.empty')} />
                    <DataTable columns={rateColumns} data={exchangeRates} emptyMessage={t('pages.accounting.currencies.emptyRates')} />
                </div>
                <div className="space-y-6">
                    <FormCard title={t('pages.accounting.currencies.createTitle')}>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                currencyForm.post(route('admin.accounting.currencies.store'));
                            }}
                            className="space-y-4"
                        >
                            <AdminFormField label={t('pages.accounting.currencies.fields.code')} error={currencyForm.errors.code} required>
                                <input
                                    id="currency_code"
                                    maxLength={3}
                                    className="rp-form-input uppercase"
                                    value={currencyForm.data.code}
                                    onChange={(e) => currencyForm.setData('code', e.target.value.toUpperCase())}
                                />
                            </AdminFormField>
                            <AdminFormField label={t('common.name')} error={currencyForm.errors.name} required>
                                <input
                                    id="currency_name"
                                    className="rp-form-input"
                                    value={currencyForm.data.name}
                                    onChange={(e) => currencyForm.setData('name', e.target.value)}
                                />
                            </AdminFormField>
                            <AdminFormField label={t('pages.accounting.currencies.fields.symbol')} error={currencyForm.errors.symbol} required>
                                <input
                                    id="currency_symbol"
                                    className="rp-form-input"
                                    value={currencyForm.data.symbol}
                                    onChange={(e) => currencyForm.setData('symbol', e.target.value)}
                                />
                            </AdminFormField>
                            <Button type="submit" disabled={currencyForm.processing}>{t('pages.accounting.currencies.createSubmit')}</Button>
                        </form>
                    </FormCard>
                    <FormCard title={t('pages.accounting.currencies.addRateTitle')}>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                rateForm.post(route('admin.accounting.currencies.rates.store'));
                            }}
                            className="space-y-4"
                        >
                            <AdminFormField label={t('common.currency')} error={rateForm.errors.currency_id} required>
                                <Select
                                    options={currencyOptions}
                                    value={rateForm.data.currency_id}
                                    onChange={(value) => rateForm.setData('currency_id', value ?? '')}
                                    placeholder={t('pages.accounting.currencies.selectCurrency')}
                                />
                            </AdminFormField>
                            <AdminFormField label={t('common.date')} error={rateForm.errors.rate_date} required>
                                <input
                                    id="rate_date"
                                    type="date"
                                    className="rp-form-input"
                                    value={rateForm.data.rate_date}
                                    onChange={(e) => rateForm.setData('rate_date', e.target.value)}
                                />
                            </AdminFormField>
                            <AdminFormField label={t('pages.accounting.currencies.fields.rate')} error={rateForm.errors.rate} required>
                                <input
                                    id="exchange_rate"
                                    type="number"
                                    step="0.000001"
                                    min="0.000001"
                                    className="rp-form-input"
                                    value={rateForm.data.rate}
                                    onChange={(e) => rateForm.setData('rate', e.target.value)}
                                />
                            </AdminFormField>
                            <AdminFormField label={t('pages.accounting.currencies.columns.rateType')} error={rateForm.errors.rate_type}>
                                <Select
                                    options={rateTypeOptions}
                                    value={rateForm.data.rate_type}
                                    onChange={(value) => rateForm.setData('rate_type', value ?? 'spot')}
                                />
                            </AdminFormField>
                            <Button type="submit" disabled={rateForm.processing}>{t('pages.accounting.currencies.addRateSubmit')}</Button>
                        </form>
                    </FormCard>
                </div>
            </div>
        </>
    );
}

export default withAdminLayout(Index);
