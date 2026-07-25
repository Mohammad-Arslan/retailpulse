import GuideLayout from '@/Layouts/GuideLayout';
import MarkdownGuideSection from '@/Components/help-support/markdown/MarkdownGuideSection';
import { parseMarkdownGuide } from '@/Components/help-support/markdown/markdownGuideUtils';
import { Head } from '@inertiajs/react';

import branchSetupMd from '../../../../../docs/user-manual-branch-setup.md?raw';

export default function BranchSetup() {
    const { docTitle, sections } = parseMarkdownGuide(branchSetupMd);

    return (
        <>
            <Head title="Branch Setup & Onboarding Guide" />
            <GuideLayout
                guideKey="branch-setup"
                guideTitle={docTitle}
                guideSubtitle="Branch Setup & Onboarding"
                hero={{
                    eyebrow: 'Guides · Branches & Organization',
                    title: 'Branch Setup & Onboarding',
                    description:
                        'The order of operations to take a brand-new branch from "just created" to selling on POS with accounting posting correctly.',
                    meta: [
                        { label: 'Source', value: 'docs/user-manual-branch-setup.md' },
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
