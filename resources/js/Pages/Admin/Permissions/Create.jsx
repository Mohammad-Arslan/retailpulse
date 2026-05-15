import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        group: '',
        description: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.permissions.store'));
    };

    return (
        <AdminLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800">
                    Create permission
                </h2>
            }
        >
            <Head title="Create permission" />

            <form onSubmit={submit} className="max-w-xl space-y-4 rounded-lg bg-white p-6 shadow">
                <div>
                    <InputLabel htmlFor="name" value="Name (e.g. users.create)" />
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
                <PrimaryButton disabled={processing}>Create</PrimaryButton>
            </form>
        </AdminLayout>
    );
}
