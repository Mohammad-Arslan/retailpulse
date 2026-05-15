import { ConfirmDialogProvider } from '@/Components/common/ConfirmDialogProvider';
import { Toaster } from '@/Components/ui/sonner';
import '@/i18n';

export default function AppProviders({ children }) {
    return (
        <ConfirmDialogProvider>
            {children}
            <Toaster position="top-right" richColors closeButton />
        </ConfirmDialogProvider>
    );
}
