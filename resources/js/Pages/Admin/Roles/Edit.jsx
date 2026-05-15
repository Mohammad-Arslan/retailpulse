import PermissionCheckboxes from '@/Components/admin/PermissionCheckboxes';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ role, permissionGroups }) {
    const can = useCan();
    const { data, setData, put, processing, errors, delete: destroy } = useForm({
        name: role.name,
        description: role.description ?? '',
        permissions: [...role.permissions],
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.roles.update', role.id));
    };

    const remove = () => {
        if (confirm('Delete this role?')) {
            destroy(route('admin.roles.destroy', role.id));
        }
    };

    return (
        <AdminLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800">Edit role</h2>
            }
        >
            <Head title="Edit role" />

            <form onSubmit={submit} className="space-y-4">
                <div className="max-w-xl rounded-lg bg-white p-6 shadow">
                    <div>
                        <InputLabel htmlFor="name" value="Name" />
                        <TextInput
                            id="name"
                            value={data.name}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            disabled={role.is_system}
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="mt-4">
                        <InputLabel htmlFor="description" value="Description" />
                        <TextInput
                            id="description"
                            value={data.description}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('description', e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-lg bg-white p-6 shadow">
                    <PermissionCheckboxes
                        permissionGroups={permissionGroups}
                        selected={data.permissions}
                        onChange={(permissions) => setData('permissions', permissions)}
                    />
                </div>

                <div className="flex gap-2">
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>
                    {can('roles.delete') && !role.is_system && (
                        <DangerButton type="button" onClick={remove}>
                            Delete
                        </DangerButton>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
