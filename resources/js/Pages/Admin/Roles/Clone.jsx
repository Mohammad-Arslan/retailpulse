import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Clone({ role }) {
    const { data, setData, post, processing, errors } = useForm({
        name: `${role.name}-copy`,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.roles.clone.store', role.id));
    };

    return (
        <AdminLayout>
            <Head title="Clone role" />

            <PageHeader
                title={`Clone: ${role.name}`}
                description="Create a copy of this role with a new name."
            >
                <Link href={route('admin.roles.index')} className="rp-btn-outline">
                    Cancel
                </Link>
            </PageHeader>

            <form onSubmit={submit}>
                <FormCard>
                    <AdminFormField
                        label="New role name"
                        id="name"
                        error={errors.name}
                    >
                        <input
                            id="name"
                            value={data.name}
                            className="rp-form-input"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    </AdminFormField>
                    <button
                        type="submit"
                        disabled={processing}
                        className="rp-btn-primary"
                    >
                        Clone role
                    </button>
                </FormCard>
            </form>
        </AdminLayout>
    );
}
