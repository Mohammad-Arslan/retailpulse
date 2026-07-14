import BranchAssignmentFields from '@/Components/admin/BranchAssignmentFields';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import AdminLayout from '@/Layouts/AdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ roles, availableBranches }) {
    const can = useCan();
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        phone: '',
        is_active: true,
        roles: [],
        branches: [],
        pos_pin: '',
        pos_pin_confirmation: '',
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
                        <AdminFormField label="Roles" error={errors.roles}>
                            <div className="rp-checkbox-group">
                                {roles.map((role) => (
                                    <label key={role.name} className="rp-checkbox-label">
                                        <input
                                            type="checkbox"
                                            checked={data.roles.includes(role.name)}
                                            onChange={() => toggleRole(role.name)}
                                            className="accent-teal-500"
                                        />
                                        <span>
                                            <span className="font-medium">
                                                {role.display_name || role.name}
                                            </span>
                                            <span className="ms-1 font-mono text-[11px] text-rp-text-muted">
                                                ({role.name})
                                            </span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </AdminFormField>
                    )}

                    <AdminFormField
                        label="POS PIN (optional)"
                        id="pos_pin"
                        error={errors.pos_pin}
                        hint="6-digit numeric PIN for POS access. Leave blank to set later."
                    >
                        <input
                            id="pos_pin"
                            type="password"
                            inputMode="numeric"
                            maxLength={6}
                            placeholder="6 digits"
                            value={data.pos_pin}
                            className="rp-form-input"
                            onChange={(e) => setData('pos_pin', e.target.value)}
                            autoComplete="new-password"
                        />
                    </AdminFormField>

                    {data.pos_pin && (
                        <AdminFormField
                            label="Confirm POS PIN"
                            id="pos_pin_confirmation"
                            error={errors.pos_pin_confirmation}
                        >
                            <input
                                id="pos_pin_confirmation"
                                type="password"
                                inputMode="numeric"
                                maxLength={6}
                                placeholder="Repeat PIN"
                                value={data.pos_pin_confirmation}
                                className="rp-form-input"
                                onChange={(e) => setData('pos_pin_confirmation', e.target.value)}
                                autoComplete="new-password"
                            />
                        </AdminFormField>
                    )}

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
