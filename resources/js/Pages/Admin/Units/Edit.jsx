import UnitFormFields from '@/Components/admin/UnitFormFields';
import PageHeader from '@/Components/common/PageHeader';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Edit({ unit }) {
    const can = useCan();
    const confirm = useConfirm();
    const { t } = useTranslation();
    const { data, setData, put, processing, errors, delete: destroy } = useForm({
        name: unit.name,
        abbreviation: unit.abbreviation,
        is_active: unit.is_active,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.units.update', unit.id));
    };

    const remove = async () => {
        const confirmed = await confirm({
            title: t('confirm.deleteTitle'),
            description: t('confirm.deleteUnit', { name: unit.name }),
            confirmLabel: t('common.delete'),
            cancelLabel: t('confirm.cancel'),
            variant: 'destructive',
        });

        if (confirmed) {
            destroy(route('admin.units.destroy', unit.id));
        }
    };

    return (
        <AdminLayout>
            <Head title={t('pages.units.editTitle', { name: unit.name })} />
            <PageHeader title={t('pages.units.editTitle', { name: unit.name })}>
                <Link href={route('admin.units.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>
            <form onSubmit={submit} className="max-w-2xl space-y-5">
                <UnitFormFields data={data} setData={setData} errors={errors} />
                <div className="flex items-center gap-3">
                    <button type="submit" disabled={processing} className="rp-btn-primary">
                        {t('pages.units.saveChanges')}
                    </button>
                    {can('products.delete') && (
                        <button type="button" onClick={remove} className="rp-btn-outline text-red-600">
                            {t('common.delete')}
                        </button>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
