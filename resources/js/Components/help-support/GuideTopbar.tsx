import { useTheme } from '@/Hooks/useTheme';
import { Link } from '@inertiajs/react';
import { Menu, Moon, Sun } from 'lucide-react';
import { useTranslation } from 'react-i18next';

type Props = {
    activeSectionTitle: string;
    onOpenMobileNav: () => void;
};

export default function GuideTopbar({ activeSectionTitle, onOpenMobileNav }: Props) {
    const { t } = useTranslation();
    const { isDark, toggleTheme } = useTheme();

    return (
        <div className="sticky top-0 z-20 flex items-center justify-between gap-3 bg-gradient-to-b from-[color:var(--g-bg)] via-[color:var(--g-bg)] to-transparent pt-6 pb-2">
            <button
                type="button"
                className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[color:var(--g-border)] bg-[color:var(--g-panel)] text-[color:var(--g-text)] lg:hidden"
                onClick={onOpenMobileNav}
                aria-label={t('common.openMenu')}
            >
                <Menu className="h-5 w-5" />
            </button>

            <div className="min-w-0 flex-1 text-[12px] text-[color:var(--g-text-faint)]">
                <span className="font-mono">RetailPulse Docs</span>
                {activeSectionTitle ? (
                    <span className="font-mono">
                        {' '}
                        <span className="opacity-70">›</span> {activeSectionTitle}
                    </span>
                ) : null}
            </div>

            <div className="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    onClick={toggleTheme}
                    className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[color:var(--g-border)] bg-[color:var(--g-panel)] text-[color:var(--g-text-dim)] transition hover:border-[color:var(--g-teal-dim)] hover:text-[color:var(--g-text)]"
                    aria-label={
                        isDark ? t('common.switchToLight') : t('common.switchToDark')
                    }
                    title={isDark ? t('common.switchToLight') : t('common.switchToDark')}
                >
                    {isDark ? (
                        <Sun className="h-4 w-4 text-amber-500" />
                    ) : (
                        <Moon className="h-4 w-4" />
                    )}
                </button>

                <Link
                    href="/help-support"
                    className="rounded-lg border border-[color:var(--g-border)] bg-[color:var(--g-panel)] px-3 py-2 text-[12.5px] font-semibold text-[color:var(--g-text-dim)] transition hover:border-[color:var(--g-teal-dim)] hover:text-[color:var(--g-text)]"
                >
                    Back to Help Center
                </Link>
            </div>
        </div>
    );
}
