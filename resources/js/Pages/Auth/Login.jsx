import BrandIcon from '@/Components/brand/BrandIcon';
import InputError from '@/Components/InputError';
import { Head, useForm } from '@inertiajs/react';
import { LogIn } from 'lucide-react';

export default function Login({ status }) {
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
        <>
            <Head title="Sign in" />

            <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-sand-100 p-4 sm:p-8">
                <div
                    className="pointer-events-none absolute inset-0"
                    style={{
                        background: `
                            radial-gradient(ellipse 600px 500px at 20% 60%, rgba(42,124,111,.12) 0%, transparent 70%),
                            radial-gradient(ellipse 400px 400px at 80% 20%, rgba(200,118,42,.08) 0%, transparent 70%),
                            #f3efe8
                        `,
                    }}
                />
                <div
                    className="pointer-events-none absolute -top-20 -right-20 h-[500px] w-[500px] rounded-full border border-sand-200 opacity-60"
                    aria-hidden
                />
                <div
                    className="pointer-events-none absolute -bottom-28 -left-14 h-[350px] w-[350px] rounded-full border border-sand-200 opacity-40"
                    aria-hidden
                />

                <div className="relative z-10 grid w-full max-w-[920px] overflow-hidden rounded-3xl bg-white shadow-[0_32px_80px_rgba(26,23,20,.12),0_4px_16px_rgba(26,23,20,.06)] lg:grid-cols-[1fr_460px]">
                    <div className="relative hidden flex-col justify-between overflow-hidden bg-ink-900 p-12 lg:flex">
                        <div className="pointer-events-none absolute -top-14 -right-14 h-[280px] w-[280px] rounded-full bg-[radial-gradient(circle,rgba(42,124,111,.3)_0%,transparent_70%)]" />
                        <div
                            className="pointer-events-none absolute bottom-10 -left-10 h-[180px] w-[180px] rounded-full bg-[radial-gradient(circle,rgba(200,118,42,.2)_0%,transparent_70%)]"
                            aria-hidden
                        />

                        <div className="relative z-10 flex items-center gap-3">
                            <BrandIcon />
                            <span className="font-display text-[22px] text-white">
                                RetailPulse
                            </span>
                        </div>

                        <div className="relative z-10">
                            <h1 className="font-display text-[38px] leading-tight font-normal text-white">
                                The <em className="text-teal-300 italic">intelligent</em>{' '}
                                heart of your retail.
                            </h1>
                            <p className="mt-4 max-w-sm text-sm leading-relaxed text-sand-300">
                                Real-time dashboards, multi-branch POS, and
                                AI-ready analytics — all unified in one elegant
                                system.
                            </p>
                        </div>

                        <div className="relative z-10 flex gap-6 border-t border-ink-800 pt-6">
                            <div>
                                <span className="font-display block text-[26px] text-white">
                                    —
                                </span>
                                <span className="text-[11px] tracking-wider text-sand-300 uppercase">
                                    Branches
                                </span>
                            </div>
                            <div>
                                <span className="font-display block text-[26px] text-white">
                                    —
                                </span>
                                <span className="text-[11px] tracking-wider text-sand-300 uppercase">
                                    Live data
                                </span>
                            </div>
                            <div>
                                <span className="font-display block text-[26px] text-white">
                                    —
                                </span>
                                <span className="text-[11px] tracking-wider text-sand-300 uppercase">
                                    Transactions
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col justify-center p-8 sm:p-12">
                        <div className="mb-8 flex items-center gap-3 lg:hidden">
                            <BrandIcon className="h-10 w-10" />
                            <span className="font-display text-xl text-ink-900">
                                RetailPulse
                            </span>
                        </div>

                        <h2 className="font-display text-[28px] font-normal text-ink-900">
                            Welcome back.
                        </h2>
                        <p className="mt-2 mb-9 text-sm text-ink-500">
                            Sign in to your RetailPulse workspace.
                        </p>

                        {status && (
                            <div className="mb-6 rounded-xl border border-teal-100 bg-teal-100/60 px-4 py-3 text-sm text-teal-500">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <label
                                    htmlFor="email"
                                    className="rp-form-label"
                                >
                                    Email address
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    autoComplete="username"
                                    autoFocus
                                    placeholder="admin@retailpulse.com"
                                    className="rp-form-input"
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.email}
                                    className="mt-2"
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="password"
                                    className="rp-form-label"
                                >
                                    Password
                                </label>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    autoComplete="current-password"
                                    placeholder="••••••••"
                                    className="rp-form-input"
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.password}
                                    className="mt-2"
                                />
                            </div>

                            <label className="flex cursor-pointer items-center gap-2 text-[13px] text-ink-500">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) =>
                                        setData('remember', e.target.checked)
                                    }
                                    className="accent-teal-500"
                                />
                                Remember this device
                            </label>

                            <button
                                type="submit"
                                disabled={processing}
                                className="rp-btn-login"
                            >
                                <LogIn className="h-4 w-4" />
                                Sign In
                            </button>
                        </form>

                        <p className="mt-8 text-center text-xs text-ink-300">
                            Admin access only. Contact your administrator for an
                            account.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
