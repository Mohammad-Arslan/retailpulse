const GRADIENTS = [
    'linear-gradient(135deg, #2a7c6f, #4db8a8)',
    'linear-gradient(135deg, #6a4ac8, #9d7fe0)',
    'linear-gradient(135deg, #c8762a, #f0a85a)',
    'linear-gradient(135deg, #c84a4a, #e88080)',
    'linear-gradient(135deg, #457090, #7ab4d8)',
];

export function getInitials(name) {
    if (!name) {
        return '?';
    }

    const parts = name.trim().split(/\s+/);

    if (parts.length >= 2) {
        return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
    }

    return name.slice(0, 2).toUpperCase();
}

export function getAvatarGradient(name) {
    const code = (name ?? '').split('').reduce((sum, char) => sum + char.charCodeAt(0), 0);

    return GRADIENTS[code % GRADIENTS.length];
}

export function rolePillVariant(roleName) {
    const normalized = (roleName ?? '').toLowerCase().replace(/\s+/g, '-');

    if (normalized.includes('super') || normalized.includes('owner')) {
        return 'super-admin';
    }

    if (normalized.includes('manager')) {
        return 'manager';
    }

    if (normalized.includes('cashier')) {
        return 'cashier';
    }

    if (normalized.includes('accountant')) {
        return 'accountant';
    }

    return 'default';
}
