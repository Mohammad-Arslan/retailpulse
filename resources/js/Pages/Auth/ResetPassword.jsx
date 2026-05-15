import FormField from '@/Components/common/FormField';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout
            title="Choose a new password"
            subtitle="Enter your new credentials below."
        >
            <Head title="Reset Password" />

            <form onSubmit={submit} className="space-y-5">
                <FormField label="Email" id="email" error={errors.email}>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        className="rp-form-input"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </FormField>

                <FormField label="Password" id="password" error={errors.password}>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="new-password"
                        autoFocus
                        className="rp-form-input"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </FormField>

                <FormField
                    label="Confirm password"
                    id="password_confirmation"
                    error={errors.password_confirmation}
                >
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        className="rp-form-input"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />
                </FormField>

                <button type="submit" disabled={processing} className="rp-btn-login">
                    Reset password
                </button>
            </form>
        </GuestLayout>
    );
}
