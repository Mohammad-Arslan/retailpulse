import BranchAssignmentFields from '@/Components/admin/BranchAssignmentFields';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Edit({ user, roles, availableBranches }) {
    const can = useCan();
    const confirm = useConfirm();
    const { t } = useTranslation();
    const { data, setData, put, processing, errors, delete: destroy } = useForm({
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        phone: user.phone ?? '',
        is_active: user.is_active,
        roles: [...user.roles],
        branches: user.branches ?? [],
    });

    const toggleRole = (role) => {
        if (data.roles.includes(role)) {
            setData(
                'roles',
                data.roles.filter((r) => r !== role),
            );
        } else {
            setData('roles', [...data.roles, role]);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.users.update', user.id));
    };

    const remove = async () => {
        const confirmed = await confirm({
            title: t('confirm.deleteTitle'),
            description: t('confirm.deleteUser', { name: user.name }),
            confirmLabel: t('common.delete'),
            cancelLabel: t('confirm.cancel'),
            variant: 'destructive',
        });

        if (confirmed) {
            destroy(route('admin.users.destroy', user.id));
        }
    };

    return (
        <AdminLayout>
            <Head title="Edit user" />

            <PageHeader title="Edit User" description={user.email}>
                <Link href={route('admin.users.index')} className="rp-btn-outline">
                    Back
                </Link>
            </PageHeader>

            <form onSubmit={submit}>
                <FormCard>
                    <AdminFormField label="Name" id="name" error={errors.name}>
                        <input
                            id="name"
                            value={data.name}
                            className="rp-form-input"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    </AdminFormField>

                    <AdminFormField label="Email" id="email" error={errors.email}>
                        <input
                            id="email"
                            type="email"
                            value={data.email}
                            className="rp-form-input"
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                    </AdminFormField>

                    <AdminFormField label="Phone" id="phone">
                        <input
                            id="phone"
                            value={data.phone}
                            className="rp-form-input"
                            onChange={(e) => setData('phone', e.target.value)}
                        />
                    </AdminFormField>

                    <AdminFormField
                        label="New password (optional)"
                        id="password"
                        error={errors.password}
                    >
                        <input
                            id="password"
                            type="password"
                            value={data.password}
                            className="rp-form-input"
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    </AdminFormField>

                    <label className="rp-checkbox-label">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) =>
                                setData('is_active', e.target.checked)
                            }
                            className="accent-teal-500"
                        />
                        Active account
                    </label>

                    {can('users.assign-branches') && (
                        <BranchAssignmentFields
                            availableBranches={availableBranches}
                            assignments={data.branches}
                            onChange={(branches) => setData('branches', branches)}
                            error={errors.branches}
                        />
                    )}

                    {can('users.assign-roles') && (
                        <AdminFormField label="Roles">
                            <div className="rp-checkbox-group">
                                {roles.map((role) => (
                                    <label key={role} className="rp-checkbox-label">
                                        <input
                                            type="checkbox"
                                            checked={data.roles.includes(role)}
                                            onChange={() => toggleRole(role)}
                                            className="accent-teal-500"
                                        />
                                        {role}
                                    </label>
                                ))}
                            </div>
                        </AdminFormField>
                    )}

                    <div className="flex flex-wrap gap-2 pt-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rp-btn-primary"
                        >
                            Save changes
                        </button>
                        {can('users.delete') && (
                            <button
                                type="button"
                                onClick={remove}
                                className="rp-btn-outline border-rose-200 text-rose-500 hover:border-rose-500 hover:bg-rose-100 hover:text-rose-500"
                            >
                                Delete user
                            </button>
                        )}
                    </div>
                </FormCard>
            </form>
        </AdminLayout>
    );
}
