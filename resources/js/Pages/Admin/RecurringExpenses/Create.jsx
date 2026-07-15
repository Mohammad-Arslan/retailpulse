import AdminFormField from '@/Components/common/AdminFormField';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Create({
    categories,
    branches,
    legalEntities,
    costCentres,
    taxTypes,
    paymentMethods,
    frequencies,
    prorationPolicies,
    statuses,
}) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        expense_category_id: '',
        branch_id: '',
        legal_entity_id: '',
        cost_centre_id: '',
        currency_code: 'USD',
        amount: '',
        tax_type_id: '',
        frequency: 'monthly',
        interval_count: 1,
        day_of_period: '',
        start_date: new Date().toISOString().slice(0, 10),
        end_date: '',
        proration_policy: 'none',
        payment_method: '',
        status: 'active',
    });

    const categoryOptions = useMemo(
        () => [
            { value: '', label: t('pages.recurringExpenses.selectCategory') },
            ...categories.map((c) => ({ value: String(c.id), label: c.name })),
        ],
        [categories, t],
    );

    const entityOptions = useMemo(
        () => [
            { value: '', label: t('pages.recurringExpenses.selectLegalEntity') },
            ...legalEntities.map((le) => ({ value: String(le.id), label: le.legal_name })),
        ],
        [legalEntities, t],
    );

    const branchOptions = useMemo(
        () => [
            { value: '', label: t('pages.recurringExpenses.selectBranch') },
            ...branches.map((b) => ({ value: String(b.id), label: b.name })),
        ],
        [branches, t],
    );

    const costCentreOptions = useMemo(
        () => [
            { value: '', label: t('pages.recurringExpenses.selectCostCentre') },
            ...costCentres.map((cc) => ({ value: String(cc.id), label: cc.name })),
        ],
        [costCentres, t],
    );

    const taxTypeOptions = useMemo(
        () => [
            { value: '', label: t('pages.recurringExpenses.selectTaxType') },
            ...taxTypes.map((tt) => ({ value: String(tt.id), label: tt.name })),
        ],
        [taxTypes, t],
    );

    const frequencyOptions = useMemo(
        () =>
            frequencies.map((f) => ({
                value: f,
                label: t(`pages.recurringExpenses.frequencies.${f}`, { defaultValue: f }),
            })),
        [frequencies, t],
    );

    const prorationPolicyOptions = useMemo(
        () =>
            prorationPolicies.map((p) => ({
                value: p,
                label: t(`pages.recurringExpenses.prorationPolicies.${p}`, { defaultValue: p }),
            })),
        [prorationPolicies, t],
    );

    const paymentMethodOptions = useMemo(
        () => [
            { value: '', label: t('pages.recurringExpenses.noPaymentMethod') },
            ...paymentMethods.map((pm) => ({
                value: pm,
                label: t(`pages.recurringExpenses.paymentMethods.${pm}`, { defaultValue: pm }),
            })),
        ],
        [paymentMethods, t],
    );

    const statusOptions = useMemo(
        () =>
            statuses.map((s) => ({
                value: s,
                label: t(`pages.recurringExpenses.statuses.${s}`, { defaultValue: s }),
            })),
        [statuses, t],
    );

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.expenses.recurring-expenses.store'));
    };

    return (
        <>
            <Head title={t('pages.recurringExpenses.createTitle')} />
            <PageHeader
                title={t('pages.recurringExpenses.createTitle')}
                description={t('pages.recurringExpenses.createDescription')}
            >
                <Link href={route('admin.expenses.recurring-expenses.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="grid max-w-3xl gap-4 sm:grid-cols-2">
                <AdminFormField
                    label={t('pages.recurringExpenses.fields.category')}
                    id="expense_category_id"
                    error={errors.expense_category_id}
                    className="sm:col-span-2"
                >
                    <Select
                        id="expense_category_id"
                        value={data.expense_category_id}
                        onChange={(value) => setData('expense_category_id', value ?? '')}
                        options={categoryOptions}
                    />
                </AdminFormField>

                <AdminFormField
                    label={t('pages.recurringExpenses.fields.legalEntity')}
                    id="legal_entity_id"
                    error={errors.legal_entity_id}
                >
                    <Select
                        id="legal_entity_id"
                        value={data.legal_entity_id}
                        onChange={(value) => {
                            const next = value ?? '';
                            const entity = legalEntities.find((le) => String(le.id) === next);
                            setData((prev) => ({
                                ...prev,
                                legal_entity_id: next,
                                currency_code: entity?.functional_currency_code ?? prev.currency_code,
                            }));
                        }}
                        options={entityOptions}
                    />
                </AdminFormField>

                <AdminFormField label={t('pages.recurringExpenses.fields.branch')} id="branch_id" error={errors.branch_id}>
                    <Select
                        id="branch_id"
                        value={data.branch_id}
                        onChange={(value) => setData('branch_id', value ?? '')}
                        options={branchOptions}
                    />
                </AdminFormField>

                <AdminFormField
                    label={t('pages.recurringExpenses.fields.costCentre')}
                    id="cost_centre_id"
                    error={errors.cost_centre_id}
                    className="sm:col-span-2"
                >
                    <Select
                        id="cost_centre_id"
                        value={data.cost_centre_id}
                        onChange={(value) => setData('cost_centre_id', value ?? '')}
                        options={costCentreOptions}
                        isClearable
                    />
                </AdminFormField>

                <AdminFormField label={t('pages.recurringExpenses.fields.amount')} id="amount" error={errors.amount}>
                    <input
                        id="amount"
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.amount}
                        onChange={(e) => setData('amount', e.target.value)}
                        className="rp-form-input"
                    />
                </AdminFormField>

                <AdminFormField
                    label={t('pages.recurringExpenses.fields.currency')}
                    id="currency_code"
                    error={errors.currency_code}
                >
                    <input
                        id="currency_code"
                        value={data.currency_code}
                        onChange={(e) => setData('currency_code', e.target.value.toUpperCase())}
                        maxLength={3}
                        className="rp-form-input"
                    />
                </AdminFormField>

                <AdminFormField
                    label={t('pages.recurringExpenses.fields.taxType')}
                    id="tax_type_id"
                    error={errors.tax_type_id}
                    className="sm:col-span-2"
                >
                    <Select
                        id="tax_type_id"
                        value={data.tax_type_id}
                        onChange={(value) => setData('tax_type_id', value ?? '')}
                        options={taxTypeOptions}
                        isClearable
                    />
                </AdminFormField>

                <AdminFormField
                    label={t('pages.recurringExpenses.fields.frequency')}
                    id="frequency"
                    error={errors.frequency}
                >
                    <Select
                        id="frequency"
                        value={data.frequency}
                        onChange={(value) => setData('frequency', value ?? 'monthly')}
                        options={frequencyOptions}
                    />
                </AdminFormField>

                <AdminFormField
                    label={t('pages.recurringExpenses.fields.intervalCount')}
                    id="interval_count"
                    error={errors.interval_count}
                >
                    <input
                        id="interval_count"
                        type="number"
                        min="1"
                        value={data.interval_count}
                        onChange={(e) => setData('interval_count', Number(e.target.value))}
                        className="rp-form-input"
                    />
                </AdminFormField>

                <AdminFormField
                    label={t('pages.recurringExpenses.fields.dayOfPeriod')}
                    id="day_of_period"
                    error={errors.day_of_period}
                    className="sm:col-span-2"
                >
                    <input
                        id="day_of_period"
                        type="number"
                        min="1"
                        max="31"
                        value={data.day_of_period}
                        onChange={(e) => setData('day_of_period', e.target.value)}
                        className="rp-form-input"
                        placeholder={t('pages.recurringExpenses.dayOfPeriodPlaceholder')}
                    />
                </AdminFormField>

                <AdminFormField
                    label={t('pages.recurringExpenses.fields.startDate')}
                    id="start_date"
                    error={errors.start_date}
                >
                    <input
                        id="start_date"
                        type="date"
                        value={data.start_date}
                        onChange={(e) => setData('start_date', e.target.value)}
                        className="rp-form-input"
                    />
                </AdminFormField>

                <AdminFormField label={t('pages.recurringExpenses.fields.endDate')} id="end_date" error={errors.end_date}>
                    <input
                        id="end_date"
                        type="date"
                        value={data.end_date}
                        onChange={(e) => setData('end_date', e.target.value)}
                        className="rp-form-input"
                    />
                </AdminFormField>

                <AdminFormField
                    label={t('pages.recurringExpenses.fields.prorationPolicy')}
                    id="proration_policy"
                    error={errors.proration_policy}
                >
                    <Select
                        id="proration_policy"
                        value={data.proration_policy}
                        onChange={(value) => setData('proration_policy', value ?? 'none')}
                        options={prorationPolicyOptions}
                    />
                </AdminFormField>

                <AdminFormField
                    label={t('pages.recurringExpenses.fields.paymentMethod')}
                    id="payment_method"
                    error={errors.payment_method}
                >
                    <Select
                        id="payment_method"
                        value={data.payment_method}
                        onChange={(value) => setData('payment_method', value ?? '')}
                        options={paymentMethodOptions}
                        isClearable
                    />
                </AdminFormField>

                <AdminFormField
                    label={t('pages.recurringExpenses.fields.status')}
                    id="status"
                    error={errors.status}
                    className="sm:col-span-2"
                >
                    <Select
                        id="status"
                        value={data.status}
                        onChange={(value) => setData('status', value ?? 'active')}
                        options={statusOptions}
                    />
                </AdminFormField>

                <div className="flex gap-2 sm:col-span-2">
                    <Button type="submit" variant="brand" disabled={processing}>
                        {t('pages.recurringExpenses.createSubmit')}
                    </Button>
                    <Link href={route('admin.expenses.recurring-expenses.index')} className="rp-btn-outline">
                        {t('confirm.cancel')}
                    </Link>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
