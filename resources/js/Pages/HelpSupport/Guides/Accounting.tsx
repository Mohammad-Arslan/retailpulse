import GuideSection from '@/Components/help-support/GuideSection';
import GuideLayout from '@/Layouts/GuideLayout';
import { accountingGuide } from '@/data/accountingGuide';
import { Head } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

export default function Accounting() {
    const sections = accountingGuide.sections as any[];

    return (
        <>
            <Head title="Accounting & Financial Management Guide" />

            <GuideLayout
                guideTitle={accountingGuide.guideTitle}
                guideSubtitle={accountingGuide.guideSubtitle}
                hero={accountingGuide.hero}
                sections={sections}
                heroExtra={
                    <div className="mt-10 flex flex-wrap items-stretch gap-0">
                        <div className="flex-1 rounded-[10px] border border-[color:var(--g-border)] bg-[color:var(--g-panel)] px-4 py-4">
                            <div className="font-mono text-[10.5px] tracking-[0.5px] text-[color:var(--g-text-faint)] uppercase">
                                Step 1
                            </div>
                            <div className="mt-2 text-[14px] font-semibold">
                                Something happens in the store
                            </div>
                            <div className="mt-1 text-[12.5px] leading-relaxed text-[color:var(--g-text-dim)]">
                                A cashier completes a sale, a warehouse receives goods, or a supplier gets paid.
                            </div>
                        </div>
                        <div className="flex w-11 items-center justify-center text-[color:var(--g-teal)] max-[820px]:w-full max-[820px]:rotate-90 max-[820px]:py-3">
                            <ChevronRight className="h-6 w-6" />
                        </div>
                        <div className="flex-1 rounded-[10px] border border-[color:var(--g-teal-dim)] bg-[color:var(--g-teal-wash)] px-4 py-4">
                            <div className="font-mono text-[10.5px] tracking-[0.5px] text-[color:var(--g-text-faint)] uppercase">
                                Step 2
                            </div>
                            <div className="mt-2 text-[14px] font-semibold">
                                RetailPulse figures out the accounting
                            </div>
                            <div className="mt-1 text-[12.5px] leading-relaxed text-[color:var(--g-text-dim)]">
                                It looks up your rules and your chart of accounts to decide which accounts to debit and credit.
                            </div>
                        </div>
                        <div className="flex w-11 items-center justify-center text-[color:var(--g-teal)] max-[820px]:w-full max-[820px]:rotate-90 max-[820px]:py-3">
                            <ChevronRight className="h-6 w-6" />
                        </div>
                        <div className="flex-1 rounded-[10px] border border-[color:var(--g-border)] bg-[color:var(--g-panel)] px-4 py-4">
                            <div className="font-mono text-[10.5px] tracking-[0.5px] text-[color:var(--g-text-faint)] uppercase">
                                Step 3
                            </div>
                            <div className="mt-2 text-[14px] font-semibold">
                                A balanced journal is posted
                            </div>
                            <div className="mt-1 text-[12.5px] leading-relaxed text-[color:var(--g-text-dim)]">
                                The result lands in your General Ledger and shows up on your reports — automatically.
                            </div>
                        </div>
                    </div>
                }
            >
                <div className="pb-16">
                    {sections.map((s, idx) => (
                        <GuideSection
                            key={s.id}
                            section={s}
                            prev={idx > 0 ? sections[idx - 1] : undefined}
                            next={idx < sections.length - 1 ? sections[idx + 1] : undefined}
                        />
                    ))}

                    <footer className="mt-16 border-t border-[color:var(--g-border-soft)] pt-6 text-[12px] text-[color:var(--g-text-faint)]">
                        RetailPulse Accounting Guide · v1.1, July 2026 — restructured for on-screen navigation from the original written manual.
                    </footer>
                </div>
            </GuideLayout>
        </>
    );
}

