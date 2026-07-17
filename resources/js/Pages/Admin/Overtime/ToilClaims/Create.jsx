import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import FormInfoPanel from '@/Components/common/FormInfoPanel';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Coins } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Create({ employees }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        employee_id: '',
        hours: '',
        reason: '',
    });

    const employeeOptions = useMemo(
        () => [
            { value: '', label: t('pages.toilClaims.selectEmployee') },
            ...employees.map((employee) => ({
                value: String(employee.id),
                label: `${employee.first_name} ${employee.last_name} (${employee.employee_code})`,
            })),
        ],
        [employees, t],
    );

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.overtime.toil-claims.store'));
    };

    return (
        <>
            <Head title={t('pages.toilClaims.createTitle')} />
            <PageHeader title={t('pages.toilClaims.createTitle')} description={t('pages.toilClaims.createDescription')}>
                <Link href={route('admin.overtime.toil-claims.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="w-full space-y-5">
                <div className="grid grid-cols-1 gap-5 xl:grid-cols-3">
                    <FormCard className="max-w-none w-full space-y-4 xl:col-span-2">
                        <h3 className="rp-section-title border-b border-rp-border pb-3">
                            {t('pages.toilClaims.fields.sectionTitle')}
                        </h3>

                        <AdminFormField
                            label={t('pages.toilClaims.fields.employee')}
                            id="employee_id"
                            error={errors.employee_id}
                            required
                        >
                            <Select
                                id="employee_id"
                                value={data.employee_id}
                                onChange={(value) => setData('employee_id', value ?? '')}
                                options={employeeOptions}
                            />
                        </AdminFormField>

                        <AdminFormField
                            label={t('pages.toilClaims.fields.hours')}
                            id="hours"
                            error={errors.hours}
                            required
                        >
                            <input
                                id="hours"
                                type="number"
                                step="0.25"
                                min="0.25"
                                value={data.hours}
                                onChange={(e) => setData('hours', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>

                        <AdminFormField label={t('pages.toilClaims.fields.reason')} id="reason" error={errors.reason}>
                            <textarea
                                id="reason"
                                value={data.reason}
                                onChange={(e) => setData('reason', e.target.value)}
                                rows={3}
                                className="rp-form-input"
                                placeholder={t('pages.toilClaims.reasonPlaceholder')}
                            />
                        </AdminFormField>

                        <div className="flex justify-end gap-3 border-t border-rp-border pt-4">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('admin.overtime.toil-claims.index')}>{t('confirm.cancel')}</Link>
                            </Button>
                            <Button type="submit" variant="brand" disabled={processing}>
                                {t('pages.toilClaims.submitRequest')}
                            </Button>
                        </div>
                    </FormCard>

                    <FormInfoPanel icon={Coins} title={t('pages.toilClaims.infoPanel.title')} className="xl:col-span-1">
                        <ol>
                            <li>{t('pages.toilClaims.infoPanel.step1')}</li>
                            <li>{t('pages.toilClaims.infoPanel.step2')}</li>
                            <li>{t('pages.toilClaims.infoPanel.step3')}</li>
                        </ol>
                    </FormInfoPanel>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
