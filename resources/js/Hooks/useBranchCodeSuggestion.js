import { useEffect, useState } from 'react';

function previewCodeFromName(name) {
    const slug = name
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .toUpperCase();

    if (!slug || slug.length < 2) {
        return 'BR';
    }

    return slug.slice(0, 28);
}

export function useBranchCodeSuggestion({ name, enabled = true }) {
    const [suggestedCode, setSuggestedCode] = useState('');
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!enabled || !name?.trim()) {
            setSuggestedCode('');
            return undefined;
        }

        const trimmed = name.trim();
        setSuggestedCode(previewCodeFromName(trimmed));

        const timer = window.setTimeout(async () => {
            if (!window.axios) {
                return;
            }

            setLoading(true);

            try {
                const { data } = await window.axios.get(route('admin.branches.suggest-code'), {
                    params: { name: trimmed },
                });

                setSuggestedCode(data.code ?? '');
            } catch {
                setSuggestedCode(previewCodeFromName(trimmed));
            } finally {
                setLoading(false);
            }
        }, 300);

        return () => window.clearTimeout(timer);
    }, [name, enabled]);

    return { suggestedCode, loading };
}
