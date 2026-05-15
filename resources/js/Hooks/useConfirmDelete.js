import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { useTranslation } from 'react-i18next';

export function useConfirmDelete() {
    const confirm = useConfirm();
    const { t } = useTranslation();

    return (name, descriptionKey = 'confirm.deleteDescription') =>
        confirm({
            title: t('confirm.deleteTitle'),
            description: t(descriptionKey, { name }),
            confirmLabel: t('common.delete'),
            cancelLabel: t('confirm.cancel'),
            variant: 'destructive',
        });
}
