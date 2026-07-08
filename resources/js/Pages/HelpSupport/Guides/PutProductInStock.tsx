import GuideLayout from '@/Layouts/GuideLayout';
import MarkdownGuideSection from '@/Components/help-support/markdown/MarkdownGuideSection';
import { parseMarkdownGuide } from '@/Components/help-support/markdown/markdownGuideUtils';
import { Head } from '@inertiajs/react';

import putStockMd from '../../../../../docs/user-manual-put-product-in-stock.md?raw';

export default function PutProductInStock() {
    const { docTitle, sections } = parseMarkdownGuide(putStockMd);

    return (
        <>
            <Head title="Put a Product in Stock Guide" />
            <GuideLayout
                guideKey="put-product-in-stock"
                guideTitle={docTitle}
                guideSubtitle="Put a Product in Stock"
                hero={{
                    eyebrow: 'Guides · Inventory Operations',
                    title: 'Put a Product in Stock (Any Branch)',
                    description:
                        'A focused, step-by-step walkthrough to make a product “in hand” (sellable) at a branch using receive, adjust, transfer, or import.',
                    meta: [
                        { label: 'Source', value: 'docs/user-manual-put-product-in-stock.md' },
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

