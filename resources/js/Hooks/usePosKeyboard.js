import { useEffect } from 'react';

/**
 * Global POS keyboard shortcut map.
 *
 * Binds:
 *  F2           → focus search
 *  F3           → focus qty of last cart item
 *  F5           → suspend / hold cart
 *  F10          → trigger checkout
 *  Ctrl+H       → suspend active cart
 *  Ctrl+V       → void active cart  (needs confirmation in handler)
 *  Ctrl+1..5    → switch cart slot
 *  Ctrl+Z       → undo last item add
 */
export function usePosKeyboard(handlers) {
    useEffect(() => {
        function onKey(e) {
            const ctrl = e.ctrlKey || e.metaKey;

            switch (true) {
                case e.key === 'F2':
                    e.preventDefault();
                    handlers.focusSearch?.();
                    break;

                case e.key === 'F3':
                    e.preventDefault();
                    handlers.focusQty?.();
                    break;

                case e.key === 'F5':
                    e.preventDefault();
                    handlers.suspendCart?.();
                    break;

                case e.key === 'F10':
                    e.preventDefault();
                    handlers.checkout?.();
                    break;

                case ctrl && e.key === 'h':
                case ctrl && e.key === 'H':
                    e.preventDefault();
                    handlers.suspendCart?.();
                    break;

                case ctrl && e.key === 'v':
                case ctrl && e.key === 'V':
                    e.preventDefault();
                    handlers.voidCart?.();
                    break;

                case ctrl && e.key === 'z':
                case ctrl && e.key === 'Z':
                    e.preventDefault();
                    handlers.undoLastItem?.();
                    break;

                case ctrl && ['1', '2', '3', '4', '5'].includes(e.key):
                    e.preventDefault();
                    handlers.switchSlot?.(parseInt(e.key, 10));
                    break;

                default:
                    break;
            }
        }

        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [handlers]);
}
