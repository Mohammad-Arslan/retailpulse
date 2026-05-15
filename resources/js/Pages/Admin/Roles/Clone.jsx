import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Clone({ role }) {
    const { data, setData, post, processing, errors } = useForm({
        name: `${role.name}-copy`,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.roles.clone.store', role.id));
    };

    return (
        <AdminLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800">
                    Clone role: {role.name}
                </h2>
            }
        >
            <Head title="Clone role" />

            <form onSubmit={submit} className="max-w-xl space-y-4 rounded-lg bg-white p-6 shadow">
                <div>
                    <InputLabel htmlFor="name" value="New role name" />
                    <TextInput
                        id="name"
                        value={data.name}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                    <InputError message={errors.name} />
                </div>
                <PrimaryButton disabled={processing}>Clone</PrimaryButton>
            </form>
        </AdminLayout>
    );
}
