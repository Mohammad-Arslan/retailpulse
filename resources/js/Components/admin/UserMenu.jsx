import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { getInitials } from '@/lib/avatar';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function UserMenu({ className }) {
    const { t } = useTranslation();
    const user = usePage().props.auth.user;
    const roles = usePage().props.auth.roles ?? [];
    const roleLabel = roles[0] ?? 'Administrator';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className={cn(
                        'h-9 gap-2 border-rp-border bg-rp-surface px-2 hover:border-teal-400',
                        className,
                    )}
                    aria-label={t('common.profile')}
                >
                    <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-linear-to-br from-teal-500 to-teal-300 text-xs font-bold text-white">
                        {getInitials(user?.name)}
                    </span>
                    <span className="hidden max-w-[120px] truncate text-sm font-medium text-rp-text lg:inline">
                        {user?.name}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="font-normal">
                    <p className="text-sm font-semibold text-rp-text">{user?.name}</p>
                    <p className="text-xs text-rp-text-muted">{user?.email}</p>
                    <p className="mt-1 text-xs text-rp-text-secondary">{roleLabel}</p>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="flex w-full cursor-pointer items-center gap-2 text-rose-500 focus:text-rose-500"
                    >
                        <LogOut className="h-4 w-4" />
                        {t('common.logOut')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
