import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, Languages } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function LanguageSwitcher({ className }) {
    const { t } = useTranslation();
    const locale = usePage().props.locale;

    if (!locale?.options?.length || locale.options.length < 2) {
        return null;
    }

    const activeLabel = locale.nativeLabel ?? locale.label ?? locale.active?.toUpperCase();

    const switchTo = (code) => {
        router.put(
            route('locale.update'),
            { locale: code },
            { preserveScroll: true },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className={cn(
                        'hidden h-9 gap-2 border-rp-border bg-rp-surface text-rp-text-secondary hover:border-teal-400 hover:text-teal-500 md:inline-flex',
                        className,
                    )}
                    aria-label={t('common.language')}
                >
                    <Languages className="h-4 w-4 shrink-0" />
                    <span className="max-w-[120px] truncate">{activeLabel}</span>
                    <ChevronDown className="h-3.5 w-3.5 opacity-60" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
                <DropdownMenuLabel>{t('common.selectLanguage')}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {locale.options.map((option) => (
                    <DropdownMenuItem
                        key={option.code}
                        onClick={() => switchTo(option.code)}
                        className="flex items-center justify-between"
                    >
                        <span>
                            {option.native}
                            {option.native !== option.label && (
                                <span className="ml-1 text-xs text-rp-text-muted">
                                    ({option.label})
                                </span>
                            )}
                        </span>
                        {locale.active === option.code && (
                            <Check className="h-4 w-4 shrink-0 text-teal-500" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
