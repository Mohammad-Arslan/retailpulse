import GuideLayout from '@/Layouts/GuideLayout';
import MarkdownGuideSection from '@/Components/help-support/markdown/MarkdownGuideSection';
import { parseMarkdownGuide } from '@/Components/help-support/markdown/markdownGuideUtils';
import { Head } from '@inertiajs/react';

import customersMd from '../../../../../docs/user-manual-customers-and-loyalty.md?raw';

export default function CustomersLoyalty() {
    const { docTitle, sections } = parseMarkdownGuide(customersMd);

    return (
        <>
            <Head title="Sales & Customers Guide" />
            <GuideLayout
                guideKey="customers-loyalty"
                guideTitle={docTitle}
                guideSubtitle="Customers & Loyalty Guide"
                hero={{
                    eyebrow: 'Guides · Customers & Loyalty',
                    title: 'Manage Customers, Loyalty, Wallet, and AR',
                    description:
                        'A click-by-click manual for customer profiles, loyalty tiers, wallet and store credit, credit limits, AR aging, and checkout integration.',
                    meta: [
                        { label: 'Source', value: 'docs/user-manual-customers-and-loyalty.md' },
                        { label: 'Sections', value: String(sections.length) },
                        { label: 'Format', value: 'Markdown Guide' },
                    ],
                }}
                sections={sections.map((s) => ({ id: s.id, num: s.num, title: s.title, intro: '', blocks: [] }))}
            >
                <div className="pb-16">
                    {sections.map((s, idx) => (
                        <MarkdownGuideSection
                            key={s.id}
                            id={s.id}
                            num={s.num}
                            title={s.title}
                            markdown={s.markdown}
                            prev={idx > 0 ? sections[idx - 1] : undefined}
                            next={idx < sections.length - 1 ? sections[idx + 1] : undefined}
                        />
                    ))}

                    <footer className="mt-16 border-t border-[color:var(--g-border-soft)] pt-6 text-[12px] text-[color:var(--g-text-faint)]">
                        Rendered from the RetailPulse markdown manual in `docs/`.
                    </footer>
                </div>
            </GuideLayout>
        </>
    );
}

