import { cn } from '@/lib/utils';
import { ChevronUp } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function BackToTopButton() {
    const [show, setShow] = useState(false);

    useEffect(() => {
        const onScroll = () => setShow(window.scrollY > 600);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    return (
        <button
            type="button"
            onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
            aria-label="Back to top"
            className={cn(
                'fixed right-7 bottom-7 z-50 flex h-11 w-11 items-center justify-center rounded-full border border-transparent bg-[color:var(--g-teal)] text-[#04231a] shadow-[0_6px_18px_rgba(0,0,0,.35)] transition',
                show ? 'opacity-100' : 'pointer-events-none opacity-0',
                show && 'hover:-translate-y-0.5',
            )}
        >
            <ChevronUp className="h-5 w-5" strokeWidth={2.4} />
        </button>
    );
}

