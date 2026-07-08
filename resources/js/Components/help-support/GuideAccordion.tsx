import type { ReactNode } from 'react';

export default function GuideAccordion({ children }: { children: ReactNode }) {
    return <div className="flex flex-col gap-2">{children}</div>;
}

