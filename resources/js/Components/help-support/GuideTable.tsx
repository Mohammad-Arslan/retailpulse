import type { ReactNode } from 'react';

export default function GuideTable({ children }: { children: ReactNode }) {
    return (
        <div className="overflow-hidden rounded-[10px] border border-[color:var(--g-border)] bg-[color:var(--g-panel)]">
            {children}
        </div>
    );
}

