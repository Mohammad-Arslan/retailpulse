import { getAvatarGradient, getInitials } from '@/lib/avatar';
import { cn } from '@/lib/utils';

export default function UserAvatar({ name, size = 'md', className }) {
    const sizes = {
        sm: 'h-9 w-9 rounded-[10px] text-[13px]',
        md: 'h-[38px] w-[38px] rounded-[10px] text-[13px]',
        lg: 'h-11 w-11 rounded-xl text-sm',
    };

    return (
        <div
            className={cn(
                'flex shrink-0 items-center justify-center font-bold text-white',
                sizes[size],
                className,
            )}
            style={{ background: getAvatarGradient(name) }}
        >
            {getInitials(name)}
        </div>
    );
}
