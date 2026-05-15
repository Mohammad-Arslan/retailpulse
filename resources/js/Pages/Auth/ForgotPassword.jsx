import FormField from '@/Components/common/FormField';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout
            title="Reset password"
            subtitle="Enter your email and we will send you a reset link."
        >
            <Head title="Forgot Password" />

            {status && (
                <div className="mb-6 rounded-xl border border-teal-100 bg-teal-100/60 px-4 py-3 text-sm text-teal-500">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <FormField label="Email address" id="email" error={errors.email}>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        className="rp-form-input"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </FormField>

                <button type="submit" disabled={processing} className="rp-btn-login">
                    Email reset link
                </button>
            </form>
        </GuestLayout>
    );
}
