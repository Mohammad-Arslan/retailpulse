import { useEffect, useState } from 'react';

function previewCodeFromName(name) {
    const slug = name
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .toUpperCase();

    if (!slug || slug.length < 2) {
        return 'WH';
    }

    return slug.slice(0, 28);
}

export function useWarehouseCodeSuggestion({ name, branchId = null, enabled = true }) {
    const [suggestedCode, setSuggestedCode] = useState('');
    const [isPreview, setIsPreview] = useState(true);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!enabled || !name?.trim()) {
            setSuggestedCode('');
            setIsPreview(true);
            return undefined;
        }

        const trimmed = name.trim();
        setSuggestedCode(previewCodeFromName(trimmed));
        setIsPreview(branchId === null || branchId === '');

        const timer = window.setTimeout(async () => {
            if (!window.axios) {
                return;
            }

            setLoading(true);

            try {
                const params = { name: trimmed };

                if (branchId) {
                    params.branch_id = branchId;
                }

                const { data } = await window.axios.get(route('admin.warehouses.suggest-code'), {
                    params,
                });

                setSuggestedCode(data.code ?? '');
                setIsPreview(Boolean(data.preview));
            } catch {
                setSuggestedCode(previewCodeFromName(trimmed));
                setIsPreview(true);
            } finally {
                setLoading(false);
            }
        }, 300);

        return () => window.clearTimeout(timer);
    }, [name, branchId, enabled]);

    return { suggestedCode, isPreview, loading };
}
