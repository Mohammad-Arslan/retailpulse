import GuideLayout from '@/Layouts/GuideLayout';
import MarkdownGuideSection from '@/Components/help-support/markdown/MarkdownGuideSection';
import { parseMarkdownGuide } from '@/Components/help-support/markdown/markdownGuideUtils';
import { Head } from '@inertiajs/react';

import inventoryMd from '../../../../../docs/user-manual-inventory-and-catalogue.md?raw';

export default function InventoryCatalogue() {
    const { docTitle, sections } = parseMarkdownGuide(inventoryMd);

    return (
        <>
            <Head title="Inventory Management Guide" />
            <GuideLayout
                guideKey="inventory-catalogue"
                guideTitle={docTitle}
                guideSubtitle="Catalogue & Inventory Guide"
                hero={{
                    eyebrow: 'Guides · Inventory Management',
                    title: 'Catalogue & Inventory Operations',
                    description:
                        'A click-by-click manual for products, variants, warehouses, stock levels, receiving, adjustments, transfers, bins, quarantine, and cycle counts.',
                    meta: [
                        { label: 'Source', value: 'docs/user-manual-inventory-and-catalogue.md' },
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

