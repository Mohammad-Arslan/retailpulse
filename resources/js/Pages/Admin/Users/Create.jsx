import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';

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
        post(route('admin.users.store'));
    };

    return (
        <AdminLayout>
            <Head title="Create user" />

            <PageHeader
                title="Create User"
                description="Add a new team member to the workspace."
            >
                <Link href={route('admin.users.index')} className="rp-btn-outline">
                    Cancel
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

                    <AdminFormField label="Phone" id="phone" error={errors.phone}>
                        <input
                            id="phone"
                            value={data.phone}
                            className="rp-form-input"
                            onChange={(e) => setData('phone', e.target.value)}
                        />
                    </AdminFormField>

                    <AdminFormField
                        label="Password"
                        id="password"
                        error={errors.password}
                    >
                        <input
                            id="password"
                            type="password"
                            value={data.password}
                            className="rp-form-input"
                            onChange={(e) => setData('password', e.target.value)}
                            required
                        />
                    </AdminFormField>

                    <AdminFormField
                        label="Confirm password"
                        id="password_confirmation"
                    >
                        <input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            className="rp-form-input"
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                            required
                        />
                    </AdminFormField>

                    <label className="flex cursor-pointer items-center gap-2 text-sm text-ink-500">
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

                    <AdminFormField label="Roles" error={errors.roles}>
                        <div className="mt-1 space-y-2 rounded-lg border border-sand-200 bg-sand-50 p-3">
                            {roles.map((role) => (
                                <label
                                    key={role}
                                    className="flex cursor-pointer items-center gap-2 text-sm text-ink-700"
                                >
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

                    <button
                        type="submit"
                        disabled={processing}
                        className="rp-btn-primary"
                    >
                        Create user
                    </button>
                </FormCard>
            </form>
        </AdminLayout>
    );
}
