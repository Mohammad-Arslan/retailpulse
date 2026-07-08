import type { ReactNode } from 'react';

export default function GuideSteps({ children }: { children: ReactNode }) {
    return <div className="flex flex-col">{children}</div>;
}

