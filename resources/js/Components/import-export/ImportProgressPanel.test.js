import '@/i18n';
import ImportProgressPanel from '@/Components/import-export/ImportProgressPanel';
import { describe, expect, test } from 'vitest';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';

function render(progress, status) {
    return renderToStaticMarkup(
        createElement(ImportProgressPanel, { progress, status, realtimeUnavailable: false }),
    );
}

describe('ImportProgressPanel', () => {
    test('validation snapshot shows Validated/Invalid, never a larger-than-processed Successful card', () => {
        const html = render(
            { phase: 'validating', total: 10000, processed: 6900, success: 6900, failed: 0, skipped: 0 },
            'validating',
        );

        // Labelled by phase — validation has not imported anything yet.
        expect(html).toContain('Validated');
        expect(html).toContain('Invalid');
        expect(html).not.toContain('Successful');
        // "Failed" as a bare label would also match inside "Invalid" only if
        // mislabelled; assert the raw outcome values render as-is.
        expect(html).toContain('>6900<');
        expect(html).toContain('>0<');

        // No "Processed" stat card at all — nothing to contradict Validated
        // against. The blended overall count only appears in the progress
        // line ("X / total records"), a distinct, clearly-overall figure.
        expect(html).not.toContain('Processed');
        expect(html).toContain('3450 / 10000');
    });

    test('processing snapshot shows Successful/Failed as raw imported-row outcomes', () => {
        const html = render(
            { phase: 'processing', total: 10000, processed: 1400, success: 1396, failed: 4, skipped: 0 },
            'processing',
        );

        expect(html).toContain('Successful');
        expect(html).toContain('Failed');
        expect(html).not.toContain('Validated');
        expect(html).not.toContain('Invalid');
        expect(html).toContain('>1396<');
        expect(html).toContain('>4<');

        // success + failed + skipped must never exceed the raw processed
        // count reported for this phase.
        expect(1396 + 4 + 0).toBeLessThanOrEqual(1400);

        expect(html).not.toContain('Processed');
    });

    test('completed status falls back to Successful/Failed labelling', () => {
        const html = render(
            { phase: 'completed', total: 10000, processed: 10000, success: 9990, failed: 10, skipped: 0 },
            'completed',
        );

        expect(html).toContain('Successful');
        expect(html).toContain('Failed');
        expect(html).not.toContain('Validated');
    });
});
