import { useCallback, useMemo, useState } from 'react';

export function useRowSelection(initialIds = []) {
    const [selectedIds, setSelectedIds] = useState(() => new Set(initialIds));

    const toggleRow = useCallback((id) => {
        setSelectedIds((current) => {
            const next = new Set(current);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    }, []);

    const toggleAll = useCallback((ids) => {
        setSelectedIds((current) => {
            const pageIds = ids.map((id) => Number(id));
            const allSelected =
                pageIds.length > 0 && pageIds.every((id) => current.has(id));

            if (allSelected) {
                const next = new Set(current);
                pageIds.forEach((id) => next.delete(id));

                return next;
            }

            const next = new Set(current);
            pageIds.forEach((id) => next.add(id));

            return next;
        });
    }, []);

    const clearSelection = useCallback(() => {
        setSelectedIds(new Set());
    }, []);

    const isSelected = useCallback((id) => selectedIds.has(id), [selectedIds]);

    const selectionState = useCallback(
        (ids) => {
            const pageIds = ids.map((id) => Number(id));

            if (pageIds.length === 0) {
                return { allSelected: false, indeterminate: false };
            }

            const selectedOnPage = pageIds.filter((id) => selectedIds.has(id));

            return {
                allSelected: selectedOnPage.length === pageIds.length,
                indeterminate:
                    selectedOnPage.length > 0 && selectedOnPage.length < pageIds.length,
            };
        },
        [selectedIds],
    );

    const selectedCount = selectedIds.size;

    const selectedArray = useMemo(() => [...selectedIds], [selectedIds]);

    return {
        selectedIds,
        selectedArray,
        selectedCount,
        toggleRow,
        toggleAll,
        clearSelection,
        isSelected,
        selectionState,
    };
}
