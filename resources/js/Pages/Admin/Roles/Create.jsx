import PermissionCheckboxes from '@/Components/admin/PermissionCheckboxes';
import InputError from '@/Components/InputError';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ permissionGroups }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        permissions: [],
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.roles.store'));
    };

    return (
        <AdminLayout>
            <Head title="Create role" />

            <PageHeader
                title="Create Role"
                description="Define a new access profile and assign permissions."
            >
                <Link href={route('admin.roles.index')} className="rp-btn-outline">
                    Cancel
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="space-y-5">
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
                    <AdminFormField label="Description" id="description">
                        <input
                            id="description"
                            value={data.description}
                            className="rp-form-input"
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                        />
                    </AdminFormField>
                </FormCard>

                <div className="rp-card max-w-4xl">
                    <h3 className="rp-form-label mb-3">Permissions</h3>
                    <PermissionCheckboxes
                        permissionGroups={permissionGroups}
                        selected={data.permissions}
                        onChange={(permissions) =>
                            setData('permissions', permissions)
                        }
                    />
                    <InputError message={errors.permissions} className="mt-2" />
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="rp-btn-primary"
                >
                    Create role
                </button>
            </form>
        </AdminLayout>
    );
}
