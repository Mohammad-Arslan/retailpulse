import FormField from '@/Components/common/FormField';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function Login({ status }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout
            title={t('auth.welcomeBack')}
            subtitle={t('auth.signInSubtitle')}
        >
            <Head title="Sign in" />

            {status && (
                <div className="mb-6 rounded-xl border border-teal-100 bg-teal-100/60 px-4 py-3 text-sm text-teal-500">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <FormField label={t('auth.email')} id="email" error={errors.email}>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        placeholder="admin@retailpulse.com"
                        className="rp-form-input"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </FormField>

                <FormField
                    label={t('auth.password')}
                    id="password"
                    error={errors.password}
                >
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        placeholder="••••••••"
                        className="rp-form-input"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </FormField>

                <label className="flex cursor-pointer items-center gap-2 text-[13px] text-ink-500">
                    <input
                        type="checkbox"
                        name="remember"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                        className="accent-teal-500"
                    />
                    {t('auth.remember')}
                </label>

                <button type="submit" disabled={processing} className="rp-btn-login">
                    <LogIn className="h-4 w-4" />
                    {t('auth.signIn')}
                </button>
            </form>

            <p className="mt-8 text-center text-xs text-ink-300">{t('auth.adminOnly')}</p>
        </GuestLayout>
    );
}
