import { useEffect } from 'react';

/**
 * Subscribes to inventory.stock.changed WebSocket events via Laravel Echo.
 * Calls onStockChanged({ variant_id, warehouse_id, available }) when triggered.
 */
export function usePosWebSocket(cartItems, onStockChanged) {
    useEffect(() => {
        if (!window.Echo || !cartItems?.length) return;

        const variantIds = [...new Set(cartItems.map((i) => i.product_variant_id))];

        const channels = variantIds.map((variantId) => {
            const channel = window.Echo.channel(`inventory.variant.${variantId}`);
            channel.listen('.inventory.stock.changed', (event) => {
                onStockChanged(event);
            });
            return channel;
        });

        return () => {
            channels.forEach((ch) => ch.stopListening('.inventory.stock.changed'));
        };
    }, [cartItems, onStockChanged]);
}
