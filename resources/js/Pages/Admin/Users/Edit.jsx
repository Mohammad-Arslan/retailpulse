import BranchAssignmentFields from '@/Components/admin/BranchAssignmentFields';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Edit({ user, roles, availableBranches }) {
    const can = useCan();
    const confirm = useConfirm();
    const { t } = useTranslation();
    const { data, setData, put, processing, errors, delete: destroy } = useForm({
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        phone: user.phone ?? '',
        is_active: user.is_active,
        roles: [...user.roles],
        branches: user.branches ?? [],
        pos_pin: '',
        pos_pin_confirmation: '',
        clear_pos_pin: false,
    });

    function resetLockout() {
        router.post(route('admin.users.reset-pos-pin-lockout', user.id));
    }

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
        put(route('admin.users.update', user.id));
    };

    const remove = async () => {
        const confirmed = await confirm({
            title: t('confirm.deleteTitle'),
            description: t('confirm.deactivateUser', { name: user.name }),
            confirmLabel: t('common.deactivate'),
            cancelLabel: t('confirm.cancel'),
            variant: 'destructive',
        });

        if (confirmed) {
            destroy(route('admin.users.destroy', user.id));
        }
    };

    return (
        <AdminLayout>
            <Head title="Edit user" />

            <PageHeader title="Edit User" description={user.email}>
                <Link href={route('admin.users.index')} className="rp-btn-outline">
                    Back
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

                    <AdminFormField label="Phone" id="phone">
                        <input
                            id="phone"
                            value={data.phone}
                            className="rp-form-input"
                            onChange={(e) => setData('phone', e.target.value)}
                        />
                    </AdminFormField>

                    <AdminFormField
                        label="New password (optional)"
                        id="password"
                        error={errors.password}
                    >
                        <input
                            id="password"
                            type="password"
                            value={data.password}
                            className="rp-form-input"
                            onChange={(e) => setData('password', e.target.value)}
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
                        <AdminFormField label="Roles">
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

                    {/* POS PIN section */}
                    <div className="border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <p className="mb-3 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            POS PIN
                        </p>

                        {/* Status badge */}
                        <div className="mb-3 flex items-center gap-3">
                            {user.has_pos_pin ? (
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                    ✓ PIN set
                                </span>
                            ) : (
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                                    No PIN configured
                                </span>
                            )}

                            {user.pos_pin_lockout?.is_locked && (
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                    🔒 Locked — {user.pos_pin_lockout.minutes_remaining} min remaining
                                </span>
                            )}

                            {can('pos.admin') && user.pos_pin_lockout?.is_locked && (
                                <button
                                    type="button"
                                    onClick={resetLockout}
                                    className="text-xs text-blue-600 underline hover:text-blue-800 dark:text-blue-400"
                                >
                                    Clear lockout
                                </button>
                            )}
                        </div>

                        <AdminFormField
                            label={user.has_pos_pin ? 'New POS PIN (leave blank to keep current)' : 'Set POS PIN'}
                            id="pos_pin"
                            error={errors.pos_pin}
                            hint="Must be exactly 6 digits."
                        >
                            <input
                                id="pos_pin"
                                type="password"
                                inputMode="numeric"
                                maxLength={6}
                                placeholder="6 digits"
                                value={data.pos_pin}
                                className="rp-form-input"
                                onChange={(e) => {
                                    setData('pos_pin', e.target.value);
                                    if (data.clear_pos_pin) setData('clear_pos_pin', false);
                                }}
                                autoComplete="new-password"
                                disabled={data.clear_pos_pin}
                            />
                        </AdminFormField>

                        {data.pos_pin && (
                            <AdminFormField
                                label="Confirm new POS PIN"
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

                        {user.has_pos_pin && !data.pos_pin && (
                            <label className="rp-checkbox-label text-sm text-rose-600 dark:text-rose-400">
                                <input
                                    type="checkbox"
                                    checked={data.clear_pos_pin}
                                    onChange={(e) => setData('clear_pos_pin', e.target.checked)}
                                    className="accent-rose-500"
                                />
                                Remove POS PIN from this account
                            </label>
                        )}
                    </div>

                    <div className="flex flex-wrap gap-2 pt-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rp-btn-primary"
                        >
                            Save changes
                        </button>
                        {can('users.delete') && (
                            <button
                                type="button"
                                onClick={remove}
                                className="rp-btn-outline border-rose-200 text-rose-500 hover:border-rose-500 hover:bg-rose-100 hover:text-rose-500"
                            >
                                Deactivate user
                            </button>
                        )}
                    </div>
                </FormCard>
            </form>
        </AdminLayout>
    );
}
