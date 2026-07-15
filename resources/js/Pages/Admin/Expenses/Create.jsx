import AdminFormField from '@/Components/common/AdminFormField';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Create({
    categories = [],
    branches = [],
    legalEntities = [],
    costCentres = [],
    taxTypes = [],
    paymentMethods = [],
}) {
    const { t } = useTranslation();
    const defaultEntity = legalEntities[0];
    const { data, setData, post, processing, errors } = useForm({
        expense_category_id: categories[0] ? String(categories[0].id) : '',
        branch_id: branches[0] ? String(branches[0].id) : '',
        legal_entity_id: defaultEntity ? String(defaultEntity.id) : '',
        cost_centre_id: '',
        currency_code: defaultEntity?.functional_currency_code ?? 'USD',
        exchange_rate: '',
        amount: '',
        tax_type_id: '',
        tax_amount: '0',
        expense_date: new Date().toISOString().slice(0, 10),
        payment_method: '',
        description: '',
    });

    const categoryOptions = useMemo(
        () => [
            { value: '', label: t('pages.expenses.selectCategory') },
            ...categories.map((c) => ({ value: String(c.id), label: `${c.name} (${c.code})` })),
        ],
        [categories, t],
    );

    const entityOptions = useMemo(
        () => legalEntities.map((le) => ({ value: String(le.id), label: le.legal_name })),
        [legalEntities],
    );

    const branchOptions = useMemo(
        () => branches.map((b) => ({ value: String(b.id), label: b.name })),
        [branches],
    );

    const costCentreOptions = useMemo(
        () => [
            { value: '', label: t('pages.expenses.selectCostCentre') },
            ...costCentres.map((cc) => ({ value: String(cc.id), label: cc.name })),
        ],
        [costCentres, t],
    );

    const taxTypeOptions = useMemo(
        () => [
            { value: '', label: t('pages.expenses.selectTaxType') },
            ...taxTypes.map((tt) => ({ value: String(tt.id), label: tt.name })),
        ],
        [taxTypes, t],
    );

    const paymentMethodOptions = useMemo(
        () => [
            { value: '', label: t('pages.expenses.noPaymentMethod') },
            ...paymentMethods.map((pm) => ({
                value: pm,
                label: t(`pages.expenses.paymentMethods.${pm}`, { defaultValue: pm }),
            })),
        ],
        [paymentMethods, t],
    );

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.expenses.expenses.store'));
    };

    return (
        <>
            <Head title={t('pages.expenses.createTitle')} />
            <PageHeader title={t('pages.expenses.createTitle')} description={t('pages.expenses.createDescription')}>
                <Link href={route('admin.expenses.expenses.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="grid max-w-3xl gap-4 sm:grid-cols-2">
                <AdminFormField
                    label={t('pages.expenses.fields.category')}
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
                    label={t('pages.expenses.fields.legalEntity')}
                    id="legal_entity_id"
                    error={errors.legal_entity_id}
                >
                    <Select
                        id="legal_entity_id"
                        value={data.legal_entity_id}
                        onChange={(value) => {
                            const next = value ?? '';
                            setData('legal_entity_id', next);
                            const entity = legalEntities.find((le) => String(le.id) === next);
                            if (entity?.functional_currency_code) {
                                setData('currency_code', entity.functional_currency_code);
                            }
                        }}
                        options={entityOptions}
                    />
                </AdminFormField>

                <AdminFormField label={t('pages.expenses.fields.branch')} id="branch_id" error={errors.branch_id}>
                    <Select
                        id="branch_id"
                        value={data.branch_id}
                        onChange={(value) => setData('branch_id', value ?? '')}
                        options={branchOptions}
                    />
                </AdminFormField>

                <AdminFormField
                    label={t('pages.expenses.fields.costCentre')}
                    id="cost_centre_id"
                    error={errors.cost_centre_id}
                >
                    <Select
                        id="cost_centre_id"
                        value={data.cost_centre_id}
                        onChange={(value) => setData('cost_centre_id', value ?? '')}
                        options={costCentreOptions}
                        isClearable
                    />
                </AdminFormField>

                <AdminFormField label={t('pages.expenses.fields.date')} id="expense_date" error={errors.expense_date}>
                    <input
                        id="expense_date"
                        type="date"
                        value={data.expense_date}
                        onChange={(e) => setData('expense_date', e.target.value)}
                        className="rp-form-input"
                    />
                </AdminFormField>

                <AdminFormField label={t('pages.expenses.fields.amount')} id="amount" error={errors.amount}>
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
                    label={t('pages.expenses.fields.currency')}
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
                    label={t('pages.expenses.fields.exchangeRate')}
                    id="exchange_rate"
                    error={errors.exchange_rate}
                >
                    <input
                        id="exchange_rate"
                        type="number"
                        step="0.000001"
                        value={data.exchange_rate}
                        onChange={(e) => setData('exchange_rate', e.target.value)}
                        className="rp-form-input"
                        placeholder="Auto"
                    />
                </AdminFormField>

                <AdminFormField label={t('pages.expenses.fields.taxType')} id="tax_type_id" error={errors.tax_type_id}>
                    <Select
                        id="tax_type_id"
                        value={data.tax_type_id}
                        onChange={(value) => setData('tax_type_id', value ?? '')}
                        options={taxTypeOptions}
                        isClearable
                    />
                </AdminFormField>

                <AdminFormField label={t('pages.expenses.fields.taxAmount')} id="tax_amount" error={errors.tax_amount}>
                    <input
                        id="tax_amount"
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.tax_amount}
                        onChange={(e) => setData('tax_amount', e.target.value)}
                        className="rp-form-input"
                    />
                </AdminFormField>

                <AdminFormField
                    label={t('pages.expenses.fields.paymentMethod')}
                    id="payment_method"
                    error={errors.payment_method}
                    className="sm:col-span-2"
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
                    label={t('pages.expenses.fields.description')}
                    id="description"
                    error={errors.description}
                    className="sm:col-span-2"
                >
                    <textarea
                        id="description"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        rows={3}
                        className="rp-form-input"
                    />
                </AdminFormField>

                <div className="flex gap-2 sm:col-span-2">
                    <Button type="submit" variant="brand" disabled={processing}>
                        {t('pages.expenses.createSubmit')}
                    </Button>
                    <Link href={route('admin.expenses.expenses.index')} className="rp-btn-outline">
                        {t('confirm.cancel')}
                    </Link>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
