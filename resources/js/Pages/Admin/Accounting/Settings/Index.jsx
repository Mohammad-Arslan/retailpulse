import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import Modal from '@/Components/Modal';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { fiscalYearStatusLabel } from '@/lib/accountingI18n';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Lock, Unlock } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ settings, fiscalYears = [], accounts = [], currencies = [], reopenRequests = [], taxTypes = [] }) {
    const can = useCan();
    const { t } = useTranslation();
    const confirm = useConfirm();

    const { data, setData, put, processing, errors } = useForm({
        functional_currency_code: settings.functional_currency_code ?? 'USD',
        fiscal_year_start_month: String(settings.fiscal_year_start_month ?? 1),
        retained_earnings_account_id: settings.retained_earnings_account_id
            ? String(settings.retained_earnings_account_id)
            : '',
        opening_balance_equity_account_id: settings.opening_balance_equity_account_id
            ? String(settings.opening_balance_equity_account_id)
            : '',
        suspense_account_id: settings.suspense_account_id ? String(settings.suspense_account_id) : '',
        rounding_account_id: settings.rounding_account_id ? String(settings.rounding_account_id) : '',
        fx_gain_account_id: settings.fx_gain_account_id ? String(settings.fx_gain_account_id) : '',
        fx_loss_account_id: settings.fx_loss_account_id ? String(settings.fx_loss_account_id) : '',
        default_inventory_valuation_method: settings.default_inventory_valuation_method ?? 'fifo',
        allow_negative_inventory: settings.allow_negative_inventory === true,
        allow_manual_journal_posting: settings.allow_manual_journal_posting !== false,
        manual_journal_approval_limit: settings.manual_journal_approval_limit ?? '',
        accounting_cutover_date: settings.accounting_cutover_date?.slice(0, 10) ?? '',
        journal_numbering_mode: settings.journal_numbering_mode ?? 'branch_fiscal',
        fiscal_year_reopen_window_hours: String(settings.fiscal_year_reopen_window_hours ?? 48),
        default_sales_tax_type_id: settings.default_sales_tax_type_id
            ? String(settings.default_sales_tax_type_id)
            : '',
        default_purchase_tax_type_id: settings.default_purchase_tax_type_id
            ? String(settings.default_purchase_tax_type_id)
            : '',
        tax_reporting_enabled: settings.tax_reporting_enabled !== false,
        tax_return_frequency: settings.tax_return_frequency ?? 'monthly',
    });

    const readOnly = !can('accounting.manage-fiscal-years');

    const accountOptions = useMemo(
        () => [
            { value: '', label: '—' },
            ...accounts.map((a) => ({
                value: String(a.id),
                label: `${a.code} — ${a.name}`,
            })),
        ],
        [accounts],
    );

    const currencyOptions = useMemo(
        () =>
            currencies.map((c) => ({
                value: c.code ?? c.value,
                label: c.label ?? c.code,
            })),
        [currencies],
    );

    const taxTypeOptions = useMemo(
        () => [
            { value: '', label: '—' },
            ...taxTypes.map((type) => ({
                value: String(type.id),
                label: `${type.code} — ${type.name}`,
            })),
        ],
        [taxTypes],
    );

    const monthOptions = useMemo(
        () =>
            Array.from({ length: 12 }, (_, i) => ({
                value: String(i + 1),
                label: new Date(2000, i, 1).toLocaleString(undefined, { month: 'long' }),
            })),
        [],
    );

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.accounting.settings.update'));
    };

    const closeYear = async (fy) => {
        const confirmed = await confirm({
            title: t('pages.accounting.settings.closeYear'),
            description: t('pages.accounting.settings.confirmClose', { name: fy.name }),
            confirmLabel: t('pages.accounting.settings.closeYear'),
            variant: 'destructive',
        });

        if (confirmed) {
            router.post(route('admin.accounting.fiscal-years.close', fy.id));
        }
    };

    const reopenForm = useForm({ reason: '' });
    const [reopenTarget, setReopenTarget] = useState(null);

    const submitReopenRequest = (e) => {
        e.preventDefault();
        if (!reopenTarget) {
            return;
        }
        reopenForm.post(route('admin.accounting.fiscal-years.reopen-request', reopenTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                setReopenTarget(null);
                reopenForm.reset();
            },
        });
    };

    const approveReopen = (requestId) => {
        router.post(route('admin.accounting.fiscal-year-reopen-requests.approve', requestId));
    };

    const rejectReopen = (requestId) => {
        router.post(route('admin.accounting.fiscal-year-reopen-requests.reject', requestId));
    };

    return (
        <>
            <Head title={t('pages.accounting.settings.title')} />
            <PageHeader
                title={t('pages.accounting.settings.title')}
                description={t('pages.accounting.settings.description')}
            />

            <form onSubmit={submit} className="space-y-6">
                <FormCard className="max-w-4xl">
                    <h3 className="font-semibold">{t('pages.accounting.settings.generalTitle')}</h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.accounting.settings.fields.functionalCurrency')}
                            id="functional_currency_code"
                            error={errors.functional_currency_code}
                        >
                            <Select
                                id="functional_currency_code"
                                value={data.functional_currency_code}
                                onChange={(value) => setData('functional_currency_code', value ?? '')}
                                options={currencyOptions}
                                disabled={readOnly}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.settings.fields.fiscalYearStartMonth')}
                            id="fiscal_year_start_month"
                            error={errors.fiscal_year_start_month}
                        >
                            <Select
                                id="fiscal_year_start_month"
                                value={data.fiscal_year_start_month}
                                onChange={(value) => setData('fiscal_year_start_month', value ?? '')}
                                options={monthOptions}
                                disabled={readOnly}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.settings.fields.cutoverDate')}
                            id="accounting_cutover_date"
                            error={errors.accounting_cutover_date}
                        >
                            <input
                                id="accounting_cutover_date"
                                type="date"
                                value={data.accounting_cutover_date}
                                onChange={(e) => setData('accounting_cutover_date', e.target.value)}
                                className="rp-form-input"
                                disabled={readOnly}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.settings.fields.fiscalYearReopenWindowHours')}
                            id="fiscal_year_reopen_window_hours"
                            error={errors.fiscal_year_reopen_window_hours}
                        >
                            <input
                                id="fiscal_year_reopen_window_hours"
                                type="number"
                                min="1"
                                max="720"
                                value={data.fiscal_year_reopen_window_hours}
                                onChange={(e) => setData('fiscal_year_reopen_window_hours', e.target.value)}
                                className="rp-form-input"
                                disabled={readOnly}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.settings.fields.journalNumbering')}
                            id="journal_numbering_mode"
                            error={errors.journal_numbering_mode}
                        >
                            <Select
                                id="journal_numbering_mode"
                                value={data.journal_numbering_mode}
                                onChange={(value) => setData('journal_numbering_mode', value ?? '')}
                                options={[
                                    { value: 'branch_fiscal', label: t('pages.accounting.settings.numbering.branchFiscal') },
                                    { value: 'global', label: t('pages.accounting.settings.numbering.global') },
                                ]}
                                disabled={readOnly}
                            />
                        </AdminFormField>
                    </div>
                </FormCard>

                <FormCard className="max-w-4xl">
                    <h3 className="font-semibold">{t('pages.accounting.settings.accountsTitle')}</h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {[
                            ['retained_earnings_account_id', 'retainedEarnings'],
                            ['opening_balance_equity_account_id', 'openingBalanceEquity'],
                            ['suspense_account_id', 'suspense'],
                            ['rounding_account_id', 'rounding'],
                            ['fx_gain_account_id', 'fxGain'],
                            ['fx_loss_account_id', 'fxLoss'],
                        ].map(([field, labelKey]) => (
                            <AdminFormField
                                key={field}
                                label={t(`pages.accounting.settings.fields.${labelKey}`)}
                                id={field}
                                error={errors[field]}
                            >
                                <Select
                                    id={field}
                                    value={data[field]}
                                    onChange={(value) => setData(field, value ?? '')}
                                    options={accountOptions}
                                    disabled={readOnly}
                                />
                            </AdminFormField>
                        ))}
                    </div>
                </FormCard>

                <FormCard className="max-w-4xl">
                    <h3 className="font-semibold">{t('pages.accounting.settings.taxTitle')}</h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.accounting.settings.fields.defaultSalesTaxType')}
                            id="default_sales_tax_type_id"
                            error={errors.default_sales_tax_type_id}
                        >
                            <Select
                                id="default_sales_tax_type_id"
                                value={data.default_sales_tax_type_id}
                                onChange={(value) => setData('default_sales_tax_type_id', value ?? '')}
                                options={taxTypeOptions}
                                disabled={readOnly}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.settings.fields.defaultPurchaseTaxType')}
                            id="default_purchase_tax_type_id"
                            error={errors.default_purchase_tax_type_id}
                        >
                            <Select
                                id="default_purchase_tax_type_id"
                                value={data.default_purchase_tax_type_id}
                                onChange={(value) => setData('default_purchase_tax_type_id', value ?? '')}
                                options={taxTypeOptions}
                                disabled={readOnly}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.settings.fields.taxReturnFrequency')}
                            id="tax_return_frequency"
                            error={errors.tax_return_frequency}
                        >
                            <Select
                                id="tax_return_frequency"
                                value={data.tax_return_frequency}
                                onChange={(value) => setData('tax_return_frequency', value ?? 'monthly')}
                                options={[
                                    { value: 'monthly', label: t('pages.accounting.settings.taxReturnFrequencies.monthly') },
                                    { value: 'quarterly', label: t('pages.accounting.settings.taxReturnFrequencies.quarterly') },
                                    { value: 'annual', label: t('pages.accounting.settings.taxReturnFrequencies.annual') },
                                ]}
                                disabled={readOnly}
                            />
                        </AdminFormField>
                        <label className="flex items-center gap-2 text-sm sm:col-span-2">
                            <input
                                type="checkbox"
                                checked={data.tax_reporting_enabled}
                                onChange={(e) => setData('tax_reporting_enabled', e.target.checked)}
                                disabled={readOnly}
                            />
                            {t('pages.accounting.settings.fields.taxReportingEnabled')}
                        </label>
                    </div>
                </FormCard>

                <FormCard className="max-w-4xl">
                    <h3 className="font-semibold">{t('pages.accounting.settings.journalTitle')}</h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="flex items-center gap-2 text-sm sm:col-span-2">
                            <input
                                type="checkbox"
                                checked={data.allow_manual_journal_posting}
                                onChange={(e) => setData('allow_manual_journal_posting', e.target.checked)}
                                disabled={readOnly}
                            />
                            {t('pages.accounting.settings.fields.allowManualPosting')}
                        </label>
                        <AdminFormField
                            label={t('pages.accounting.settings.fields.approvalLimit')}
                            id="manual_journal_approval_limit"
                            error={errors.manual_journal_approval_limit}
                        >
                            <input
                                id="manual_journal_approval_limit"
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.manual_journal_approval_limit}
                                onChange={(e) => setData('manual_journal_approval_limit', e.target.value)}
                                className="rp-form-input"
                                disabled={readOnly}
                            />
                        </AdminFormField>
                    </div>
                </FormCard>

                <FormCard className="max-w-4xl">
                    <h3 className="font-semibold">{t('pages.accounting.settings.inventoryTitle')}</h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.accounting.settings.fields.defaultValuationMethod')}
                            id="default_inventory_valuation_method"
                            error={errors.default_inventory_valuation_method}
                        >
                            <Select
                                id="default_inventory_valuation_method"
                                value={data.default_inventory_valuation_method}
                                onChange={(value) =>
                                    setData('default_inventory_valuation_method', value ?? 'fifo')
                                }
                                options={[
                                    {
                                        value: 'fifo',
                                        label: t('pages.accounting.settings.valuationMethods.fifo'),
                                    },
                                    {
                                        value: 'wac',
                                        label: t('pages.accounting.settings.valuationMethods.wac'),
                                    },
                                ]}
                                disabled={readOnly}
                            />
                        </AdminFormField>
                        <label className="flex items-center gap-2 text-sm sm:col-span-2">
                            <input
                                type="checkbox"
                                checked={data.allow_negative_inventory}
                                onChange={(e) => setData('allow_negative_inventory', e.target.checked)}
                                disabled={readOnly}
                            />
                            {t('pages.accounting.settings.fields.allowNegativeInventory')}
                        </label>
                        <p className="text-sm text-amber-700 dark:text-amber-400 sm:col-span-2">
                            {t('pages.accounting.settings.negativeInventoryWarning')}
                        </p>
                    </div>
                </FormCard>

                {!readOnly && (
                    <div className="flex justify-end">
                        <Button type="submit" variant="brand" disabled={processing}>
                            {t('common.save')}
                        </Button>
                    </div>
                )}
            </form>

            <section className="mt-8">
                <h2 className="mb-4 text-lg font-semibold">{t('pages.accounting.settings.fiscalYearsTitle')}</h2>
                <div className="overflow-hidden rounded-lg border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('pages.accounting.settings.fiscalYearColumns.name')}</TableHead>
                                <TableHead>{t('pages.accounting.settings.fiscalYearColumns.period')}</TableHead>
                                <TableHead>{t('common.status')}</TableHead>
                                <TableHead>{t('pages.accounting.settings.fiscalYearColumns.closedAt')}</TableHead>
                                <TableHead>{t('common.actions')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {fiscalYears.length ? (
                                fiscalYears.map((fy) => (
                                    <TableRow key={fy.id}>
                                        <TableCell className="font-medium">{fy.name}</TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {fy.start_date?.slice(0, 10)} — {fy.end_date?.slice(0, 10)}
                                        </TableCell>
                                        <TableCell>
                                            <span className="capitalize">
                                                {fiscalYearStatusLabel(t, fy.status)}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {fy.closed_at ? new Date(fy.closed_at).toLocaleDateString() : '—'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-2">
                                                {fy.status === 'open' && can('accounting.close-fiscal-year') && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => closeYear(fy)}
                                                    >
                                                        <Lock className="h-3.5 w-3.5" />
                                                        {t('pages.accounting.settings.closeYear')}
                                                    </Button>
                                                )}
                                                {fy.status === 'closed' && can('accounting.reopen-fiscal-year') && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => {
                                                            setReopenTarget(fy);
                                                            reopenForm.setData('reason', '');
                                                        }}
                                                    >
                                                        <Unlock className="h-3.5 w-3.5" />
                                                        {t('pages.accounting.settings.requestReopen')}
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                                        {t('pages.accounting.settings.fiscalYearsEmpty')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </section>

            {reopenRequests.length > 0 && (
                <section className="mt-8">
                    <h2 className="mb-4 text-lg font-semibold">{t('pages.accounting.settings.reopenRequestsTitle')}</h2>
                    <div className="space-y-3">
                        {reopenRequests.map((req) => (
                            <div key={req.id} className="rounded-lg border bg-card p-4 text-sm">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-medium">{req.fiscal_year_name}</p>
                                        <p className="mt-1 text-muted-foreground">{req.reason}</p>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {t('pages.accounting.settings.requestedBy', { name: req.requested_by_name })}
                                            {req.first_approved_by_name &&
                                                ` · ${t('pages.accounting.settings.firstApproval', { name: req.first_approved_by_name })}`}
                                        </p>
                                    </div>
                                    {can('accounting.reopen-fiscal-year') && (
                                        <div className="flex gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => approveReopen(req.id)}
                                            >
                                                {req.first_approved_by
                                                    ? t('pages.accounting.settings.secondApproval')
                                                    : t('pages.accounting.settings.firstApprovalAction')}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => rejectReopen(req.id)}
                                            >
                                                {t('pages.accounting.settings.rejectReopen')}
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            <Modal show={Boolean(reopenTarget)} onClose={() => setReopenTarget(null)} maxWidth="md">
                {reopenTarget && (
                    <form onSubmit={submitReopenRequest} className="p-6">
                        <h3 className="text-lg font-semibold">
                            {t('pages.accounting.settings.requestReopenTitle', { name: reopenTarget.name })}
                        </h3>
                        <AdminFormField
                            label={t('pages.accounting.settings.reopenReasonLabel')}
                            id="reopen_reason"
                            error={reopenForm.errors.reason}
                            className="mt-4"
                        >
                            <textarea
                                id="reopen_reason"
                                value={reopenForm.data.reason}
                                onChange={(e) => reopenForm.setData('reason', e.target.value)}
                                className="rp-form-input min-h-[100px]"
                                placeholder={t('pages.accounting.settings.reopenReasonPlaceholder')}
                                required
                            />
                        </AdminFormField>
                        <div className="mt-4 flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setReopenTarget(null)}>
                                {t('common.cancel')}
                            </Button>
                            <Button type="submit" variant="brand" disabled={reopenForm.processing}>
                                {t('common.submit')}
                            </Button>
                        </div>
                    </form>
                )}
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
