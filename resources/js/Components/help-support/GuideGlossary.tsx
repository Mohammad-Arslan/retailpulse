import type { ReactNode } from 'react';

export default function GuideGlossary({ children }: { children: ReactNode }) {
    return <dl className="grid grid-cols-1 gap-0">{children}</dl>;
}

