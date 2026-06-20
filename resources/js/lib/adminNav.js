/**
 * Whether a sidebar nav item should appear active for the current route.
 * Exact sibling routeNames take priority over wildcard patterns (e.g. admin.inventory.*).
 *
 * @param {{ routeName?: string, activeRoutes?: string[] }} item
 * @param {Array<{ routeName?: string }>} sectionItems
 */
export function isAdminNavItemActive(item, sectionItems) {
    if (item.activeRoutes?.length) {
        return item.activeRoutes.some((name) => route().current(name));
    }

    const isWildcard = item.routeName?.includes('*');

    if (isWildcard) {
        const exactSiblingMatch = sectionItems.some(
            (sibling) =>
                sibling !== item
                && sibling.routeName
                && !sibling.routeName.includes('*')
                && route().current(sibling.routeName),
        );

        if (exactSiblingMatch) {
            return false;
        }
    }

    return route().current(item.routeName);
}
