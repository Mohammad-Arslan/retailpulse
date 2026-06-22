import i18n from '@/i18n';
import { useEffect } from 'react';

export default function LocaleSync({ locale }) {
    useEffect(() => {
        const code = locale?.active;
        if (!code) {
            return;
        }

        if (i18n.language !== code) {
            i18n.changeLanguage(code);
        }

        document.documentElement.lang = code;
        document.documentElement.dir = locale.rtl ? 'rtl' : 'ltr';
    }, [locale?.active, locale?.rtl]);

    return null;
}
