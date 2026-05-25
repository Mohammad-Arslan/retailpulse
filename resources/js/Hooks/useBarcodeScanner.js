import { useEffect, useRef } from 'react';

const BARCODE_THRESHOLD_MS = 50;
const MIN_BARCODE_LENGTH = 6;

/**
 * Detects barcode scanner input by distinguishing rapid sequential keystrokes
 * (≤50ms gap) from normal keyboard typing. Calls onScan(barcode) when detected.
 */
export function useBarcodeScanner(onScan, enabled = true) {
    const bufferRef = useRef('');
    const lastKeyTimeRef = useRef(0);
    const timerRef = useRef(null);

    useEffect(() => {
        if (!enabled) return;

        function handleKeyDown(e) {
            if (e.target && ['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
                return;
            }

            const now = Date.now();
            const gap = now - lastKeyTimeRef.current;
            lastKeyTimeRef.current = now;

            if (e.key === 'Enter' || e.key === '\t') {
                if (bufferRef.current.length >= MIN_BARCODE_LENGTH) {
                    const barcode = bufferRef.current;
                    bufferRef.current = '';
                    clearTimeout(timerRef.current);
                    onScan(barcode);
                }
                return;
            }

            if (e.key.length === 1) {
                if (gap > BARCODE_THRESHOLD_MS && bufferRef.current.length > 0) {
                    bufferRef.current = '';
                }

                bufferRef.current += e.key;

                clearTimeout(timerRef.current);
                timerRef.current = setTimeout(() => {
                    bufferRef.current = '';
                }, 300);
            }
        }

        window.addEventListener('keydown', handleKeyDown);
        return () => {
            window.removeEventListener('keydown', handleKeyDown);
            clearTimeout(timerRef.current);
        };
    }, [enabled, onScan]);
}
