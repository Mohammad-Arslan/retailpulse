export type GuideKey = 'accounting' | 'customers-loyalty' | 'inventory-catalogue' | 'put-product-in-stock';

type SectionLike = {
    title?: string | null;
    menu?: string | null;
};

const SKIP_TITLES = /^(table of contents|document history|glossary|appendix|abbreviations)$/i;

/** Extra curated prompts per guide (still topic-specific). */
const CURATED: Record<GuideKey, string[]> = {
    accounting: [
        'How Does A Sale Become A Journal Entry?',
        'What Should I Check Before Troubleshooting Accounting?',
        'How Do I Set Up Branches, Sub-Modules, And Permissions?',
        'Where Do I Find Chart Of Accounts And Fiscal Periods?',
        'What Are Credit And Debit Notes?',
        'How Does Bank Reconciliation Work?',
        'What Reports Should I Review First?',
    ],
    'customers-loyalty': [
        'How Do I Create And Manage Customers?',
        'How Do Customer Groups Affect Pricing?',
        'How Does The Loyalty Program Work?',
        'How Do Wallet And Store Credit Work At Checkout?',
        'How Are Credit Limits And Accounts Receivable Handled?',
        'What Should I Check If Loyalty Points Are Wrong?',
        'How Do I Import Or Export Customers?',
    ],
    'inventory-catalogue': [
        'How Do I Set Up Products And Variants?',
        'How Do Warehouses And Branches Affect Stock?',
        'How Do I Receive Stock Into Inventory?',
        'How Do Stock Adjustments Work?',
        'How Do I Transfer Stock Between Branches?',
        'How Do Cycle Counts And Quarantine Work?',
        'What Should I Check If Stock Levels Look Wrong?',
    ],
    'put-product-in-stock': [
        'What Does “In Hand” Mean On POS?',
        'What Is The Recommended Way To Put A Product In Stock?',
        'How Do I Receive Stock At A Branch?',
        'When Should I Use A Positive Stock Adjustment?',
        'How Do I Transfer Stock From Another Branch?',
        'How Do I Verify A Product Is Sellable On POS?',
        'What Should I Check If The Product Is Not Sellable?',
    ],
};

function cleanTitle(raw: string): string {
    return raw
        .replace(/&amp;/g, '&')
        .replace(/^\d+(\.\d+)*\s*[.:–—-]?\s*/, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function toTitleCaseQuestion(topic: string): string {
    const cleaned = cleanTitle(topic);
    if (!cleaned) return '';
    if (/[?？]$/.test(cleaned)) return cleaned;
    return `How Do I Work With ${cleaned}?`;
}

export function buildGuideQuestions(guideKey: GuideKey, sections: SectionLike[] = []): string[] {
    const fromSections = sections
        .map((s) => cleanTitle(String(s.menu || s.title || '')))
        .filter((title) => title !== '' && !SKIP_TITLES.test(title))
        .map(toTitleCaseQuestion)
        .filter(Boolean);

    const merged = [...CURATED[guideKey], ...fromSections];
    const seen = new Set<string>();
    const unique: string[] = [];

    for (const q of merged) {
        const key = q.toLowerCase();
        if (seen.has(key)) continue;
        seen.add(key);
        unique.push(q);
    }

    return unique;
}

export function pickRotatingSuggestions(all: string[], offset: number, count = 3): string[] {
    if (all.length === 0) return [];
    if (all.length <= count) return all;

    const start = ((offset % all.length) + all.length) % all.length;
    const picked: string[] = [];

    for (let i = 0; i < count; i += 1) {
        picked.push(all[(start + i) % all.length]);
    }

    return picked;
}

export function filterTypeaheadSuggestions(all: string[], query: string, limit = 5): string[] {
    const q = query.trim().toLowerCase();
    if (q.length < 2) return [];

    return all
        .filter((item) => item.toLowerCase().includes(q) && item.toLowerCase() !== q)
        .slice(0, limit);
}
