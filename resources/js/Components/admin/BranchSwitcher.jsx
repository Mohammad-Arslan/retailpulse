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
import { Building2, Check, ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function BranchSwitcher({ className }) {
    const { t } = useTranslation();
    const branch = usePage().props.branch;

    if (!branch?.options?.length && !branch?.canViewAll) {
        return null;
    }

    const label = branch.isAllBranches
        ? t('common.allBranches')
        : (branch.active?.name ?? t('common.branch'));

    const switchTo = (branchId) => {
        router.put(
            route('admin.branch-context.update'),
            { branch_id: branchId },
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
                    aria-label={t('common.branch')}
                >
                    <Building2 className="h-4 w-4 shrink-0" />
                    <span className="max-w-[140px] truncate">{label}</span>
                    <ChevronDown className="h-3.5 w-3.5 opacity-60" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-56">
                <DropdownMenuLabel>{t('common.branch')}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {branch.canViewAll && (
                    <DropdownMenuItem
                        onClick={() => switchTo(null)}
                        className="flex items-center justify-between"
                    >
                        {t('common.allBranches')}
                        {branch.isAllBranches && (
                            <Check className="h-4 w-4 text-teal-500" />
                        )}
                    </DropdownMenuItem>
                )}
                {branch.options.map((option) => (
                    <DropdownMenuItem
                        key={option.id}
                        onClick={() => switchTo(option.id)}
                        className="flex items-center justify-between"
                    >
                        <span className="truncate">
                            {option.name}
                            <span className="ml-1 text-xs text-rp-text-muted">
                                ({option.code})
                            </span>
                        </span>
                        {branch.active?.id === option.id && (
                            <Check className="h-4 w-4 shrink-0 text-teal-500" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
