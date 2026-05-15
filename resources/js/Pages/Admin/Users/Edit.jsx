import Checkbox from '@/Components/Checkbox';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ user, roles }) {
    const can = useCan();
    const { data, setData, put, processing, errors, delete: destroy } = useForm({
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        phone: user.phone ?? '',
        is_active: user.is_active,
        roles: [...user.roles],
    });

    const toggleRole = (role) => {
        if (data.roles.includes(role)) {
            setData('roles', data.roles.filter((r) => r !== role));
        } else {
            setData('roles', [...data.roles, role]);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.users.update', user.id));
    };

    const remove = () => {
        if (confirm('Delete this user?')) {
            destroy(route('admin.users.destroy', user.id));
        }
    };

    return (
        <AdminLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800">Edit user</h2>
            }
        >
            <Head title="Edit user" />

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
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    <InputError message={errors.email} />
                </div>
                <div>
                    <InputLabel htmlFor="phone" value="Phone" />
                    <TextInput
                        id="phone"
                        value={data.phone}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('phone', e.target.value)}
                    />
                </div>
                <div>
                    <InputLabel htmlFor="password" value="New password (optional)" />
                    <TextInput
                        id="password"
                        type="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} />
                </div>
                <label className="flex items-center gap-2">
                    <Checkbox
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                    />
                    <InputLabel value="Active" className="!mb-0" />
                </label>
                {can('users.assign-roles') && (
                    <div>
                        <InputLabel value="Roles" />
                        <div className="mt-2 space-y-2">
                            {roles.map((role) => (
                                <label key={role} className="flex items-center gap-2">
                                    <Checkbox
                                        checked={data.roles.includes(role)}
                                        onChange={() => toggleRole(role)}
                                    />
                                    <span className="text-sm">{role}</span>
                                </label>
                            ))}
                        </div>
                    </div>
                )}
                <div className="flex gap-2">
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>
                    {can('users.delete') && (
                        <DangerButton type="button" onClick={remove}>
                            Delete
                        </DangerButton>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
