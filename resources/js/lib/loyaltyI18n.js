/**
 * Resolve loyalty enum values to Title Case labels via i18n.
 */
export function loyaltyRuleTypeLabel(t, type) {
    if (!type) return '';
    const key = `pages.loyalty.enums.ruleTypes.${type}`;
    const label = t(key);
    return label === key ? type.replace(/_/g, ' ') : label;
}

export function loyaltyTierQualificationLabel(t, type) {
    if (!type) return '';
    const key = `pages.loyalty.enums.tierQualification.${type}`;
    const label = t(key);
    return label === key ? type.replace(/_/g, ' ') : label;
}

export function loyaltyApprovalActionLabel(t, type) {
    if (!type) return '';
    const key = `pages.loyalty.enums.approvalActions.${type}`;
    const label = t(key);
    return label === key ? type.replace(/_/g, ' ') : label;
}

export function loyaltyExpiryTypeLabel(t, type) {
    if (!type) return '';
    const key = `pages.loyalty.enums.expiryTypes.${type}`;
    const label = t(key);
    return label === key ? type.replace(/_/g, ' ') : label;
}

export function loyaltyCampaignTypeLabel(t, type) {
    if (!type) return '';
    const key = `pages.loyalty.enums.campaignTypes.${type}`;
    const label = t(key);
    return label === key ? type.replace(/_/g, ' ') : label;
}

export function loyaltyCampaignStatusLabel(t, status) {
    if (!status) return '';
    const key = `pages.loyalty.enums.campaignStatuses.${status}`;
    const label = t(key);
    return label === key ? status.replace(/_/g, ' ') : label;
}

export function idsToCommaString(ids) {
    if (!Array.isArray(ids) || ids.length === 0) return '';
    return ids.join(', ');
}

export function commaStringToIds(value) {
    if (!value || typeof value !== 'string') return [];
    return value
        .split(',')
        .map((s) => parseInt(s.trim(), 10))
        .filter((n) => !Number.isNaN(n));
}
