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
import { Building2, ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function BranchSwitcher({ className }) {
    const { t } = useTranslation();

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
                    aria-label={t('common.branch')}
                >
                    <Building2 className="h-4 w-4 shrink-0" />
                    <span className="max-w-[140px] truncate">
                        {t('common.allBranches')}
                    </span>
                    <ChevronDown className="h-3.5 w-3.5 opacity-60" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-56">
                <DropdownMenuLabel>{t('common.branch')}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem disabled className="text-rp-text-muted">
                    {t('common.allBranches')}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <p className="px-2 py-1.5 text-xs text-rp-text-muted">
                    {t('common.branchSwitcherHint')}
                </p>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
