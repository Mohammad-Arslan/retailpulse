/**
 * Resolve accounting enum / config values to Title Case labels via i18n.
 */
export function titleCaseEnum(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return String(value)
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join(' ');
}

export function chartOfAccountTypeLabel(t, type) {
    if (!type) {
        return '';
    }

    const key = `pages.accounting.chartOfAccounts.types.${type}`;
    const label = t(key);

    return label === key ? titleCaseEnum(type) : label;
}

export function journalEntryStatusLabel(t, status) {
    if (!status) {
        return '';
    }

    const key = `pages.accounting.journalEntries.statuses.${status}`;
    const label = t(key);

    return label === key ? titleCaseEnum(status) : label;
}

export function accountingEventStatusLabel(t, status) {
    if (!status) {
        return '';
    }

    const key = `pages.accounting.events.statuses.${status}`;
    const label = t(key);

    return label === key ? titleCaseEnum(status) : label;
}

export function fiscalYearStatusLabel(t, status) {
    if (!status) {
        return '';
    }

    const key = `pages.accounting.settings.fiscalYearStatuses.${status}`;
    const label = t(key);

    return label === key ? titleCaseEnum(status) : label;
}

export function accountResolutionTypeLabel(t, type) {
    if (!type) {
        return '';
    }

    const key = `pages.accounting.postingRules.resolutionTypes.${type}`;
    const label = t(key);

    return label === key ? titleCaseEnum(type) : label;
}

export function amountSourceLabel(t, source) {
    if (!source) {
        return '';
    }

    const key = `pages.accounting.postingRules.amountSources.${source}`;
    const label = t(key);

    return label === key ? titleCaseEnum(source) : label;
}

export function postingRuleEntrySideLabel(t, side) {
    if (!side) {
        return '';
    }

    const key = `pages.accounting.postingRules.entrySides.${side}`;
    const label = t(key);

    return label === key ? titleCaseEnum(side) : label;
}

export function mappingKeyLabel(t, key) {
    if (!key) {
        return '';
    }

    const i18nKey = `pages.accounting.accountMappings.keys.${key}`;
    const label = t(i18nKey);

    return label === i18nKey ? titleCaseEnum(key) : label;
}

export function accountTypeBadgeClass(type) {
    const map = {
        asset: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
        liability: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
        equity: 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-300',
        revenue: 'bg-teal-100 text-teal-800 dark:bg-teal-500/20 dark:text-teal-300',
        expense: 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-300',
    };

    return map[type] ?? 'bg-muted text-muted-foreground';
}

export function journalStatusBadgeClass(status) {
    const map = {
        draft: 'bg-muted text-muted-foreground',
        pending_approval: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
        approved: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
        posted: 'bg-teal-100 text-teal-800 dark:bg-teal-500/20 dark:text-teal-300',
        reversed: 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-300',
    };

    return map[status] ?? 'bg-muted text-muted-foreground';
}

export function eventStatusBadgeClass(status) {
    const map = {
        pending: 'bg-muted text-muted-foreground',
        processing: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
        completed: 'bg-teal-100 text-teal-800 dark:bg-teal-500/20 dark:text-teal-300',
        failed: 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-300',
        skipped: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    };

    return map[status] ?? 'bg-muted text-muted-foreground';
}
