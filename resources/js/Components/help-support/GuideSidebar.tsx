import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import styles from '@/Layouts/GuideLayout.module.css';
import type { AccountingGuideSection } from '@/data/accountingGuide';
import { useEffect, useMemo, useRef } from 'react';
import BrandIcon from '@/Components/brand/BrandIcon';

type Props = {
    guideTitle: string;
    guideSubtitle: string;
    sections: AccountingGuideSection[];
    filteredSections: AccountingGuideSection[];
    activeSectionId: string;
    mobileOpen: boolean;
    onCloseMobile: () => void;
    search: string;
    onSearchChange: (value: string) => void;
};

export default function GuideSidebar({
    guideTitle,
    guideSubtitle,
    sections,
    filteredSections,
    activeSectionId,
    mobileOpen,
    onCloseMobile,
    search,
    onSearchChange,
}: Props) {
    const scrollRef = useRef<HTMLDivElement | null>(null);

    const hint = useMemo(() => {
        const q = search.trim();
        if (!q) return `${sections.length} sections`;
        return `${filteredSections.length} match${filteredSections.length === 1 ? '' : 'es'}`;
    }, [filteredSections.length, search, sections.length]);

    useEffect(() => {
        const scroller = scrollRef.current;
        if (!scroller) return;
        const active = scroller.querySelector<HTMLAnchorElement>(
            `a[data-id="${CSS.escape(activeSectionId)}"]`,
        );
        if (!active) return;

        const r = active.getBoundingClientRect();
        const pr = scroller.getBoundingClientRect();
        if (r.top < pr.top || r.bottom > pr.bottom) {
            active.scrollIntoView({ block: 'nearest' });
        }
    }, [activeSectionId]);

    return (
        <aside
            className={cn(
                'fixed top-0 left-0 z-40 flex h-screen w-[296px] flex-col border-r border-[color:var(--g-border-soft)] bg-[color:var(--g-panel-2)] lg:sticky lg:translate-x-0',
                mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            )}
        >
            <div className="border-b border-[color:var(--g-border-soft)] px-5 pt-6 pb-4">
                <Link href="/help-support" className="flex items-center gap-3 no-underline">
                    <BrandIcon className="h-[34px] w-[34px] rounded-[9px]" iconClassName="h-[16px] w-[16px]" />
                    <div className="min-w-0">
                        <div className="font-display text-[19px] leading-tight">
                            Retail<span className="font-semibold text-[color:var(--g-teal)]">Pulse</span>
                        </div>
                    </div>
                </Link>
                <div className="mt-1 text-[11.5px] tracking-[0.4px] text-[color:var(--g-text-faint)] uppercase">
                    {guideSubtitle}
                </div>
            </div>

            <div className="border-b border-[color:var(--g-border-soft)] p-4">
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-[color:var(--g-text-faint)] opacity-70" />
                    <input
                        value={search}
                        onChange={(e) => onSearchChange(e.target.value)}
                        placeholder="Search this guide…"
                        className="w-full rounded-lg border border-[color:var(--g-border)] bg-[color:var(--g-panel)] px-3 py-2 pl-8 text-[13px] text-[color:var(--g-text)] outline-none focus:border-[color:var(--g-teal-dim)]"
                    />
                </div>
                <div className="mt-1.5 pl-0.5 text-[11px] text-[color:var(--g-text-faint)]">
                    {hint}
                </div>
            </div>

            <div
                ref={scrollRef}
                className={cn('flex-1 overflow-y-auto px-2 pb-6 pt-2', styles.scrollbar)}
            >
                <div className="px-2 pt-3 pb-1 text-[10.5px] tracking-[0.7px] text-[color:var(--g-text-faint)] uppercase">
                    Contents
                </div>

                {filteredSections.map((s) => (
                    <a
                        key={s.id}
                        href={`#${s.id}`}
                        data-id={s.id}
                        onClick={() => onCloseMobile()}
                        className={cn(
                            'flex items-center gap-2.5 rounded-[7px] px-2.5 py-2 text-[13.4px] text-[color:var(--g-text-dim)] no-underline transition',
                            'hover:bg-[color:var(--g-panel)] hover:text-[color:var(--g-text)]',
                            activeSectionId === s.id
                                ? 'bg-[color:var(--g-teal-wash)] text-[color:var(--g-teal)]'
                                : '',
                        )}
                    >
                        <span className={cn(
                            'w-5 shrink-0 font-mono text-[11px] text-[color:var(--g-text-faint)]',
                            activeSectionId === s.id ? 'text-[color:var(--g-teal)]' : '',
                        )}>
                            {s.num}
                        </span>
                        <span className="min-w-0 truncate">{s.title}</span>
                    </a>
                ))}
            </div>

            <div className="border-t border-[color:var(--g-border-soft)] p-3">
                <div className="text-xs text-[color:var(--g-text-faint)]">
                    {guideTitle}
                </div>
            </div>
        </aside>
    );
}

