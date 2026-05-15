import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';

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
        <AdminLayout>
            <Head title="Create permission" />

            <PageHeader
                title="Create Permission"
                description="Add a new granular capability to the system."
            >
                <Link
                    href={route('admin.permissions.index')}
                    className="rp-btn-outline"
                >
                    Cancel
                </Link>
            </PageHeader>

            <form onSubmit={submit}>
                <FormCard>
                    <AdminFormField
                        label="Name (e.g. users.create)"
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
                    <AdminFormField label="Group" id="group">
                        <input
                            id="group"
                            value={data.group}
                            className="rp-form-input"
                            onChange={(e) => setData('group', e.target.value)}
                        />
                    </AdminFormField>
                    <AdminFormField label="Description" id="description">
                        <input
                            id="description"
                            value={data.description}
                            className="rp-form-input"
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                        />
                    </AdminFormField>
                    <button
                        type="submit"
                        disabled={processing}
                        className="rp-btn-primary"
                    >
                        Create permission
                    </button>
                </FormCard>
            </form>
        </AdminLayout>
    );
}
