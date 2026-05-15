import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ roles }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        phone: '',
        is_active: true,
        roles: [],
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
        post(route('admin.users.store'));
    };

    return (
        <AdminLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800">Create user</h2>
            }
        >
            <Head title="Create user" />

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
                    <InputError message={errors.phone} />
                </div>
                <div>
                    <InputLabel htmlFor="password" value="Password" />
                    <TextInput
                        id="password"
                        type="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    <InputError message={errors.password} />
                </div>
                <div>
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Confirm password"
                    />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        className="mt-1 block w-full"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        required
                    />
                </div>
                <label className="flex items-center gap-2">
                    <Checkbox
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                    />
                    <InputLabel value="Active" className="!mb-0" />
                </label>
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
                    <InputError message={errors.roles} />
                </div>
                <PrimaryButton disabled={processing}>Create</PrimaryButton>
            </form>
        </AdminLayout>
    );
}
