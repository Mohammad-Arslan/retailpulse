import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
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

            <form onSubmit={submit} className="grid max-w-3xl gap-4">
                <div>
                    <label className="rp-label">{t('pages.recurringExpenses.fields.category')}</label>
                    <Select
                        value={data.expense_category_id}
                        onChange={(e) => setData('expense_category_id', e.target.value)}
                        className="w-full"
                    >
                        <option value="">{t('pages.recurringExpenses.selectCategory')}</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </Select>
                    {errors.expense_category_id && (
                        <p className="mt-1 text-sm text-red-600">{errors.expense_category_id}</p>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className="rp-label">{t('pages.recurringExpenses.fields.legalEntity')}</label>
                        <Select
                            value={data.legal_entity_id}
                            onChange={(e) => {
                                const entity = legalEntities.find((le) => String(le.id) === e.target.value);
                                setData((prev) => ({
                                    ...prev,
                                    legal_entity_id: e.target.value,
                                    currency_code: entity?.functional_currency_code ?? prev.currency_code,
                                }));
                            }}
                            className="w-full"
                        >
                            <option value="">{t('pages.recurringExpenses.selectLegalEntity')}</option>
                            {legalEntities.map((le) => (
                                <option key={le.id} value={le.id}>
                                    {le.legal_name}
                                </option>
                            ))}
                        </Select>
                        {errors.legal_entity_id && (
                            <p className="mt-1 text-sm text-red-600">{errors.legal_entity_id}</p>
                        )}
                    </div>
                    <div>
                        <label className="rp-label">{t('pages.recurringExpenses.fields.branch')}</label>
                        <Select
                            value={data.branch_id}
                            onChange={(e) => setData('branch_id', e.target.value)}
                            className="w-full"
                        >
                            <option value="">{t('pages.recurringExpenses.selectBranch')}</option>
                            {branches.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </Select>
                        {errors.branch_id && <p className="mt-1 text-sm text-red-600">{errors.branch_id}</p>}
                    </div>
                </div>

                <div>
                    <label className="rp-label">{t('pages.recurringExpenses.fields.costCentre')}</label>
                    <Select
                        value={data.cost_centre_id}
                        onChange={(e) => setData('cost_centre_id', e.target.value)}
                        className="w-full"
                    >
                        <option value="">{t('pages.recurringExpenses.selectCostCentre')}</option>
                        {costCentres.map((cc) => (
                            <option key={cc.id} value={cc.id}>
                                {cc.name}
                            </option>
                        ))}
                    </Select>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label className="rp-label">{t('pages.recurringExpenses.fields.amount')}</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            className="rp-input w-full"
                        />
                        {errors.amount && <p className="mt-1 text-sm text-red-600">{errors.amount}</p>}
                    </div>
                    <div>
                        <label className="rp-label">{t('pages.recurringExpenses.fields.currency')}</label>
                        <input
                            value={data.currency_code}
                            onChange={(e) => setData('currency_code', e.target.value.toUpperCase())}
                            maxLength={3}
                            className="rp-input w-full"
                        />
                    </div>
                    <div>
                        <label className="rp-label">{t('pages.recurringExpenses.fields.taxType')}</label>
                        <Select
                            value={data.tax_type_id}
                            onChange={(e) => setData('tax_type_id', e.target.value)}
                            className="w-full"
                        >
                            <option value="">{t('pages.recurringExpenses.selectTaxType')}</option>
                            {taxTypes.map((tt) => (
                                <option key={tt.id} value={tt.id}>
                                    {tt.name}
                                </option>
                            ))}
                        </Select>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label className="rp-label">{t('pages.recurringExpenses.fields.frequency')}</label>
                        <Select
                            value={data.frequency}
                            onChange={(e) => setData('frequency', e.target.value)}
                            className="w-full"
                        >
                            {frequencies.map((f) => (
                                <option key={f} value={f}>
                                    {t(`pages.recurringExpenses.frequencies.${f}`, { defaultValue: f })}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div>
                        <label className="rp-label">{t('pages.recurringExpenses.fields.intervalCount')}</label>
                        <input
                            type="number"
                            min="1"
                            value={data.interval_count}
                            onChange={(e) => setData('interval_count', Number(e.target.value))}
                            className="rp-input w-full"
                        />
                    </div>
                    <div>
                        <label className="rp-label">{t('pages.recurringExpenses.fields.dayOfPeriod')}</label>
                        <input
                            type="number"
                            min="1"
                            max="31"
                            value={data.day_of_period}
                            onChange={(e) => setData('day_of_period', e.target.value)}
                            className="rp-input w-full"
                            placeholder={t('pages.recurringExpenses.dayOfPeriodPlaceholder')}
                        />
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className="rp-label">{t('pages.recurringExpenses.fields.startDate')}</label>
                        <input
                            type="date"
                            value={data.start_date}
                            onChange={(e) => setData('start_date', e.target.value)}
                            className="rp-input w-full"
                        />
                        {errors.start_date && <p className="mt-1 text-sm text-red-600">{errors.start_date}</p>}
                    </div>
                    <div>
                        <label className="rp-label">{t('pages.recurringExpenses.fields.endDate')}</label>
                        <input
                            type="date"
                            value={data.end_date}
                            onChange={(e) => setData('end_date', e.target.value)}
                            className="rp-input w-full"
                        />
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label className="rp-label">{t('pages.recurringExpenses.fields.prorationPolicy')}</label>
                        <Select
                            value={data.proration_policy}
                            onChange={(e) => setData('proration_policy', e.target.value)}
                            className="w-full"
                        >
                            {prorationPolicies.map((p) => (
                                <option key={p} value={p}>
                                    {t(`pages.recurringExpenses.prorationPolicies.${p}`, { defaultValue: p })}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div>
                        <label className="rp-label">{t('pages.recurringExpenses.fields.paymentMethod')}</label>
                        <Select
                            value={data.payment_method}
                            onChange={(e) => setData('payment_method', e.target.value)}
                            className="w-full"
                        >
                            <option value="">{t('pages.recurringExpenses.noPaymentMethod')}</option>
                            {paymentMethods.map((pm) => (
                                <option key={pm} value={pm}>
                                    {t(`pages.recurringExpenses.paymentMethods.${pm}`, { defaultValue: pm })}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div>
                        <label className="rp-label">{t('pages.recurringExpenses.fields.status')}</label>
                        <Select
                            value={data.status}
                            onChange={(e) => setData('status', e.target.value)}
                            className="w-full"
                        >
                            {statuses.map((s) => (
                                <option key={s} value={s}>
                                    {t(`pages.recurringExpenses.statuses.${s}`, { defaultValue: s })}
                                </option>
                            ))}
                        </Select>
                    </div>
                </div>

                <div className="flex gap-3">
                    <button type="submit" disabled={processing} className="rp-btn-primary">
                        {t('pages.recurringExpenses.createSubmit')}
                    </button>
                    <Link href={route('admin.expenses.recurring-expenses.index')} className="rp-btn-outline">
                        {t('common.cancel')}
                    </Link>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
