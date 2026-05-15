import { ConfirmDialogProvider } from '@/Components/common/ConfirmDialogProvider';
import { Toaster } from '@/Components/ui/sonner';
import { useFlashToasts } from '@/Hooks/useFlashToasts';
import '@/i18n';

function FlashToasts() {
    useFlashToasts();

    return null;
}

export default function AppProviders({ children }) {
    return (
        <ConfirmDialogProvider>
            {children}
            <Toaster position="top-right" richColors closeButton />
            <FlashToasts />
        </ConfirmDialogProvider>
    );
}
