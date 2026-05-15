import PermissionCheckboxes from '@/Components/admin/PermissionCheckboxes';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';

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
        <AdminLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800">Create role</h2>
            }
        >
            <Head title="Create role" />

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
                    <InputLabel value="Permissions" />
                    <PermissionCheckboxes
                        permissionGroups={permissionGroups}
                        selected={data.permissions}
                        onChange={(permissions) => setData('permissions', permissions)}
                    />
                    <InputError message={errors.permissions} />
                </div>

                <PrimaryButton disabled={processing}>Create</PrimaryButton>
            </form>
        </AdminLayout>
    );
}
