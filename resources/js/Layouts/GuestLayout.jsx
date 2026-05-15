import BrandIcon from '@/Components/brand/BrandIcon';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children, title, subtitle }) {
    return (
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
                aria-hidden
            />

            <div className="relative z-10 grid w-full max-w-[920px] overflow-hidden rounded-3xl bg-white shadow-[0_32px_80px_rgba(26,23,20,.12),0_4px_16px_rgba(26,23,20,.06)] lg:grid-cols-[1fr_460px]">
                <aside className="relative hidden flex-col justify-between overflow-hidden bg-ink-900 p-12 lg:flex">
                    <div className="pointer-events-none absolute -top-14 -right-14 h-[280px] w-[280px] rounded-full bg-[radial-gradient(circle,rgba(42,124,111,.3)_0%,transparent_70%)]" />
                    <div
                        className="pointer-events-none absolute bottom-10 -left-10 h-[180px] w-[180px] rounded-full bg-[radial-gradient(circle,rgba(200,118,42,.2)_0%,transparent_70%)]"
                        aria-hidden
                    />

                    <Link
                        href={route('login')}
                        className="relative z-10 flex items-center gap-3"
                    >
                        <BrandIcon />
                        <span className="font-display text-[22px] text-white">
                            RetailPulse
                        </span>
                    </Link>

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
                </aside>

                <div className="flex flex-col justify-center p-8 sm:p-12">
                    <div className="mb-8 flex items-center gap-3 lg:hidden">
                        <BrandIcon className="h-10 w-10" />
                        <span className="font-display text-xl text-ink-900">
                            RetailPulse
                        </span>
                    </div>

                    {title && (
                        <h2 className="font-display text-[28px] font-normal text-ink-900">
                            {title}
                        </h2>
                    )}
                    {subtitle && (
                        <p className="mt-2 mb-9 text-sm text-ink-500">{subtitle}</p>
                    )}

                    {children}
                </div>
            </div>
        </div>
    );
}
