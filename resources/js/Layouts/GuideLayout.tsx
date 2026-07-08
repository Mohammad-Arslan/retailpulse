import BackToTopButton from '@/Components/help-support/BackToTopButton';
import GuideAskAiPanel from '@/Components/help-support/GuideAskAiPanel';
import GuideSidebar from '@/Components/help-support/GuideSidebar';
import GuideTopbar from '@/Components/help-support/GuideTopbar';
import type { AccountingGuideSection } from '@/data/accountingGuide';
import { useEffect, useMemo, useState } from 'react';
import styles from './GuideLayout.module.css';

type Props = {
    guideKey: 'accounting' | 'customers-loyalty' | 'inventory-catalogue' | 'put-product-in-stock';
    guideTitle: string;
    guideSubtitle: string;
    hero: {
        eyebrow: string;
        title: string;
        description: string;
        meta: Array<{ label: string; value: string }>;
    };
    sections: AccountingGuideSection[];
    heroExtra?: React.ReactNode;
    children: React.ReactNode;
};

export default function GuideLayout({
    guideKey,
    guideTitle,
    guideSubtitle,
    hero,
    sections,
    heroExtra,
    children,
}: Props) {
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const [activeSectionId, setActiveSectionId] = useState<string>(sections[0]?.id ?? '');
    const [search, setSearch] = useState('');

    const filteredSections = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return sections;
        return sections.filter((s) => s.title.toLowerCase().includes(q));
    }, [sections, search]);

    useEffect(() => {
        if (!sections.length) return;

        const targets = sections
            .map((s) => document.getElementById(s.id))
            .filter(Boolean) as HTMLElement[];

        const io = new IntersectionObserver(
            (entries) => {
                for (const e of entries) {
                    if (e.isIntersecting) {
                        setActiveSectionId(e.target.id);
                        break;
                    }
                }
            },
            { rootMargin: '-15% 0px -70% 0px', threshold: 0 },
        );

        targets.forEach((el) => io.observe(el));

        return () => io.disconnect();
    }, [sections]);

    return (
        <div className={styles.root}>
            <div className="flex min-h-screen">
                <GuideSidebar
                    guideTitle={guideTitle}
                    guideSubtitle={guideSubtitle}
                    sections={sections}
                    filteredSections={filteredSections}
                    activeSectionId={activeSectionId}
                    mobileOpen={mobileNavOpen}
                    onCloseMobile={() => setMobileNavOpen(false)}
                    search={search}
                    onSearchChange={setSearch}
                />

                {mobileNavOpen && (
                    <button
                        type="button"
                        className="fixed inset-0 z-30 bg-black/50 lg:hidden"
                        onClick={() => setMobileNavOpen(false)}
                        aria-label="Close menu"
                    />
                )}

                <main className="min-w-0 flex-1 px-5 pb-24 lg:px-14">
                    <GuideTopbar
                        activeSectionTitle={
                            sections.find((s) => s.id === activeSectionId)?.title ?? ''
                        }
                        onOpenMobileNav={() => setMobileNavOpen(true)}
                    />

                    <section className="border-b border-[color:var(--g-border-soft)] py-16">
                        <div className="text-[12px] font-semibold tracking-[0.12em] text-[color:var(--g-teal)] uppercase">
                            {hero.eyebrow}
                        </div>
                        <h1 className="mt-3 font-display text-[32px] leading-[1.1] font-normal lg:text-[44px]">
                            {hero.title}
                        </h1>
                        <p className="mt-4 max-w-[56ch] text-[16px] text-[color:var(--g-text-dim)]">
                            {hero.description}
                        </p>

                        <div className="mt-7 flex flex-wrap gap-5 text-[12.5px] text-[color:var(--g-text-faint)]">
                            {hero.meta.map((m) => (
                                <span key={m.label}>
                                    <b className="font-medium text-[color:var(--g-text-dim)]">
                                        {m.label}
                                    </b>{' '}
                                    {m.value}
                                </span>
                            ))}
                        </div>

                        {heroExtra}
                    </section>

                    {children}

                    <BackToTopButton />
                </main>
            </div>

            <GuideAskAiPanel
                guideKey={guideKey}
                sections={sections.map((s) => ({
                    title: s.title,
                    menu: s.menu ?? null,
                }))}
            />
        </div>
    );
}

