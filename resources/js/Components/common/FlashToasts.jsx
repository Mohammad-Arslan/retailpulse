import { useFlashToasts } from '@/Hooks/useFlashToasts';

/**
 * Must render inside Inertia `<App>` (PageContext). Mounted from `app.jsx` render-prop.
 */
export default function FlashToasts() {
    useFlashToasts();

    return null;
}
