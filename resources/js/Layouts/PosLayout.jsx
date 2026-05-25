import { Head } from '@inertiajs/react';

export default function PosLayout({ title, children }) {
    return (
        <>
            <Head title={title || 'POS'} />
            <div className="flex h-screen flex-col overflow-hidden bg-rp-page text-rp-text">
                {children}
            </div>
        </>
    );
}
