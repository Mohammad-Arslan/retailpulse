import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ permission }) {
    const can = useCan();
    const { data, setData, put, processing, errors, delete: destroy } = useForm({
        name: permission.name,
        group: permission.group ?? '',
        description: permission.description ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.permissions.update', permission.id));
    };

    const remove = () => {
        if (confirm('Delete this permission?')) {
            destroy(route('admin.permissions.destroy', permission.id));
        }
    };

    return (
        <AdminLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800">
                    Edit permission
                </h2>
            }
        >
            <Head title="Edit permission" />

            <form onSubmit={submit} className="max-w-xl space-y-4 rounded-lg bg-white p-6 shadow">
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
                <div>
                    <InputLabel htmlFor="group" value="Group" />
                    <TextInput
                        id="group"
                        value={data.group}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('group', e.target.value)}
                    />
                </div>
                <div>
                    <InputLabel htmlFor="description" value="Description" />
                    <TextInput
                        id="description"
                        value={data.description}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('description', e.target.value)}
                    />
                </div>
                <div className="flex gap-2">
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>
                    {can('permissions.delete') && (
                        <DangerButton type="button" onClick={remove}>
                            Delete
                        </DangerButton>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
