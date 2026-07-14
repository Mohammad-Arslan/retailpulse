import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
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
        expense_category_id: categories[0]?.id ?? '',
        branch_id: branches[0]?.id ?? '',
        legal_entity_id: defaultEntity?.id ?? '',
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
                <div className="sm:col-span-2">
                    <label className="rp-label">{t('pages.expenses.fields.category')}</label>
                    <Select
                        value={data.expense_category_id}
                        onChange={(e) => setData('expense_category_id', e.target.value)}
                        className="w-full"
                    >
                        <option value="">{t('pages.expenses.selectCategory')}</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name} ({c.code})
                            </option>
                        ))}
                    </Select>
                    {errors.expense_category_id && <p className="text-xs text-red-600">{errors.expense_category_id}</p>}
                </div>

                <div>
                    <label className="rp-label">{t('pages.expenses.fields.legalEntity')}</label>
                    <Select
                        value={data.legal_entity_id}
                        onChange={(e) => {
                            const entity = legalEntities.find((le) => String(le.id) === e.target.value);
                            setData('legal_entity_id', e.target.value);
                            if (entity?.functional_currency_code) {
                                setData('currency_code', entity.functional_currency_code);
                            }
                        }}
                        className="w-full"
                    >
                        {legalEntities.map((le) => (
                            <option key={le.id} value={le.id}>
                                {le.legal_name}
                            </option>
                        ))}
                    </Select>
                    {errors.legal_entity_id && <p className="text-xs text-red-600">{errors.legal_entity_id}</p>}
                </div>

                <div>
                    <label className="rp-label">{t('pages.expenses.fields.branch')}</label>
                    <Select
                        value={data.branch_id}
                        onChange={(e) => setData('branch_id', e.target.value)}
                        className="w-full"
                    >
                        {branches.map((b) => (
                            <option key={b.id} value={b.id}>
                                {b.name}
                            </option>
                        ))}
                    </Select>
                    {errors.branch_id && <p className="text-xs text-red-600">{errors.branch_id}</p>}
                </div>

                <div>
                    <label className="rp-label">{t('pages.expenses.fields.costCentre')}</label>
                    <Select
                        value={data.cost_centre_id}
                        onChange={(e) => setData('cost_centre_id', e.target.value)}
                        className="w-full"
                    >
                        <option value="">{t('pages.expenses.selectCostCentre')}</option>
                        {costCentres.map((cc) => (
                            <option key={cc.id} value={cc.id}>
                                {cc.name}
                            </option>
                        ))}
                    </Select>
                </div>

                <div>
                    <label className="rp-label">{t('pages.expenses.fields.date')}</label>
                    <input
                        type="date"
                        value={data.expense_date}
                        onChange={(e) => setData('expense_date', e.target.value)}
                        className="rp-input w-full"
                    />
                    {errors.expense_date && <p className="text-xs text-red-600">{errors.expense_date}</p>}
                </div>

                <div>
                    <label className="rp-label">{t('pages.expenses.fields.amount')}</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.amount}
                        onChange={(e) => setData('amount', e.target.value)}
                        className="rp-input w-full"
                    />
                    {errors.amount && <p className="text-xs text-red-600">{errors.amount}</p>}
                </div>

                <div>
                    <label className="rp-label">{t('pages.expenses.fields.currency')}</label>
                    <input
                        value={data.currency_code}
                        onChange={(e) => setData('currency_code', e.target.value.toUpperCase())}
                        maxLength={3}
                        className="rp-input w-full"
                    />
                    {errors.currency_code && <p className="text-xs text-red-600">{errors.currency_code}</p>}
                </div>

                <div>
                    <label className="rp-label">{t('pages.expenses.fields.exchangeRate')}</label>
                    <input
                        type="number"
                        step="0.000001"
                        value={data.exchange_rate}
                        onChange={(e) => setData('exchange_rate', e.target.value)}
                        className="rp-input w-full"
                        placeholder="Auto"
                    />
                </div>

                <div>
                    <label className="rp-label">{t('pages.expenses.fields.taxType')}</label>
                    <Select
                        value={data.tax_type_id}
                        onChange={(e) => setData('tax_type_id', e.target.value)}
                        className="w-full"
                    >
                        <option value="">{t('pages.expenses.selectTaxType')}</option>
                        {taxTypes.map((tt) => (
                            <option key={tt.id} value={tt.id}>
                                {tt.name}
                            </option>
                        ))}
                    </Select>
                </div>

                <div>
                    <label className="rp-label">{t('pages.expenses.fields.taxAmount')}</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.tax_amount}
                        onChange={(e) => setData('tax_amount', e.target.value)}
                        className="rp-input w-full"
                    />
                </div>

                <div className="sm:col-span-2">
                    <label className="rp-label">{t('pages.expenses.fields.paymentMethod')}</label>
                    <Select
                        value={data.payment_method}
                        onChange={(e) => setData('payment_method', e.target.value)}
                        className="w-full"
                    >
                        <option value="">{t('pages.expenses.noPaymentMethod')}</option>
                        {paymentMethods.map((pm) => (
                            <option key={pm} value={pm}>
                                {t(`pages.expenses.paymentMethods.${pm}`, { defaultValue: pm })}
                            </option>
                        ))}
                    </Select>
                </div>

                <div className="sm:col-span-2">
                    <label className="rp-label">{t('pages.expenses.fields.description')}</label>
                    <textarea
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        rows={3}
                        className="rp-input w-full"
                    />
                </div>

                <div className="flex gap-2 sm:col-span-2">
                    <Button type="submit" disabled={processing}>
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
