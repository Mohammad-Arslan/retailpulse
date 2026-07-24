import {
    AlertCircle,
    BarChart3,
    BookOpen,
    Building2,
    Boxes,
    Briefcase,
    CalendarClock,
    CalendarDays,
    ClipboardList,
    FileInput,
    FileSpreadsheet,
    FolderTree,
    Gift,
    KeyRound,
    Landmark,
    LayoutDashboard,
    Link2,
    Monitor,
    Receipt,
    ScrollText,
    Settings2,
    Layers,
    Coins,
    Package,
    Scale,
    Shield,
    ShieldAlert,
    Tag,
    Timer,
    Truck,
    UserRound,
    Users,
    UsersRound,
    Wallet,
    Warehouse,
    LifeBuoy,
    LayoutDashboard as FallbackIcon,
} from 'lucide-react';

/**
 * Maps PHP NavigationRegistry icon keys to Lucide components.
 * Shared by sidebar and Global Search.
 */
export const NAV_ICON_MAP = {
    'alert-circle': AlertCircle,
    'bar-chart-3': BarChart3,
    'book-open': BookOpen,
    'building-2': Building2,
    boxes: Boxes,
    'calendar-clock': CalendarClock,
    'calendar-days': CalendarDays,
    briefcase: Briefcase,
    'clipboard-list': ClipboardList,
    'file-input': FileInput,
    'file-spreadsheet': FileSpreadsheet,
    'folder-tree': FolderTree,
    gift: Gift,
    'key-round': KeyRound,
    landmark: Landmark,
    'layout-dashboard': LayoutDashboard,
    'link-2': Link2,
    monitor: Monitor,
    receipt: Receipt,
    'scroll-text': ScrollText,
    'settings-2': Settings2,
    layers: Layers,
    coins: Coins,
    package: Package,
    scale: Scale,
    shield: Shield,
    'shield-alert': ShieldAlert,
    tag: Tag,
    timer: Timer,
    truck: Truck,
    'user-round': UserRound,
    users: Users,
    'users-round': UsersRound,
    wallet: Wallet,
    warehouse: Warehouse,
    'life-buoy': LifeBuoy,
};

export function resolveNavIcon(iconKey) {
    return NAV_ICON_MAP[iconKey] ?? FallbackIcon;
}

/**
 * Attach Lucide components to server-composed navigation sections.
 *
 * @param {Array<{ labelKey: string, items: Array }>} sections
 */
export function withNavIcons(sections = []) {
    return sections.map((section) => ({
        ...section,
        items: (section.items ?? []).map((item) => ({
            ...item,
            icon: resolveNavIcon(item.icon),
            iconKey: item.icon,
        })),
    }));
}
