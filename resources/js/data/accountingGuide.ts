export type AccountingGuideSection = {
    id: string;
    num: string;
    title: string;
    menu?: string | null;
    intro: string;
    blocks: any[];
};

import accountingGuideExtraSections from './accountingGuide.sections.json';

export const accountingGuide = {
    guideTitle: 'RetailPulse Accounting Guide · v1.1, July 2026',
    guideSubtitle: 'Accounting & Finance Guide',
    hero: {
        eyebrow: 'Phase 11 · General Ledger & Auto-Posting',
        title: 'How money moves through RetailPulse',
        description:
            "A plain-language guide to the accounting module — where to click, how a sale turns into a journal entry, and what to do when something looks wrong.",
        meta: [
            { label: 'Version', value: '1.1 · July 2026' },
            { label: 'Audience', value: 'Accountants, finance managers, support staff' },
            { label: 'Reading time', value: '~25 min, or jump to any section' },
        ],
    },
    sections: [
        // NOTE: This data is sourced from docs/RetailPulse-Accounting-Guide.html (SECTIONS array),
        // and intentionally kept as rich HTML strings for exact content parity.
        {
            id: 's1',
            num: '1',
            title: 'Before you start',
            menu: null,
            intro:
                "Three things determine what you can see and do in Accounting: which <b>branch</b> you've selected, which <b>sub-modules</b> are turned on for that branch, and your <b>permissions</b>. Get these three right before troubleshooting anything else.",
            blocks: [
                {
                    type: 'steps',
                    title: 'The three gates you pass through',
                    items: [
                        " <b>Branch context.</b> Accounting is branch-aware. If you're logged in as super-admin with <b>All Branches</b> selected, you only see the core screens — sub-modules like Tax Types or Bank stay hidden because they're switched on per branch, not globally.",
                        " <b>Sub-module enabled.</b> Beyond permissions, a menu item like Cost Centres or Petty Cash only appears if that module is switched on for the active branch. New branches start with only <b>Core</b> enabled — everything else is opt-in.",
                        " <b>Permission granted.</b> Even with the branch and module right, your role needs the matching permission (see the full list in Section 20).",
                    ],
                },
                {
                    type: 'table',
                    title: 'What each sub-module unlocks',
                    headers: ['Module key', 'Unlocks'],
                    rows: [
                        [
                            'core',
                            'Always on — Chart of Accounts, Mappings, Rules, Journals, Settings, Reports, Events',
                        ],
                        ['ar_ap', 'Required before Credit Notes can be enabled'],
                        ['tax', 'Tax Types'],
                        ['cost_centres', 'Cost Centres'],
                        ['multi_currency', 'Currencies'],
                        ['bank_reconciliation', 'Bank Accounts, Bank Reconciliation'],
                        ['petty_cash', 'Petty Cash'],
                        ['cheques', 'Cheques'],
                        ['fixed_assets', 'Fixed Assets'],
                        ['credit_notes', 'Credit Notes (also needs ar_ap)'],
                        ['intercompany', 'Intercompany (also needs multi_currency)'],
                    ],
                },
                {
                    type: 'note',
                    tone: 'info',
                    text: "<b>Turning modules on today:</b> there's no toggle screen yet — an admin enables modules per branch directly on the <code>BranchAccountingProfile</code> record (see the tinker snippet in Section 21.7). A proper on-screen toggle is planned for a later phase.",
                },
                {
                    type: 'whatif',
                    title: "If a sub-module isn't enabled…",
                    items: [
                        {
                            cause: 'You visit its page anyway',
                            effect: 'You get a plain 403 Forbidden — the menu item is hidden, and the direct link is blocked too.',
                        },
                        {
                            cause: 'An event for that area still fires (e.g. a cheque status changes)',
                            effect: "Auto-posting can still run behind the scenes even if the admin screen is locked — the data pipeline and the UI gate are separate.",
                        },
                    ],
                },
                {
                    type: 'table',
                    title: 'The six principles the system always enforces',
                    headers: ['Principle', 'What it means for you'],
                    rows: [
                        [
                            'Double-entry',
                            "Every posted journal has equal debits and credits — the system won't let an unbalanced one through.",
                        ],
                        [
                            'Immutability',
                            "Once posted, a journal can't be edited. Mistakes are fixed with a reversal, not a rewrite.",
                        ],
                        [
                            'Idempotency',
                            'The same sale or payment can never accidentally create two journal entries, even if the system retries.',
                        ],
                        [
                            'Configuration, not hardcoding',
                            "Account numbers live in your Chart of Accounts and rules — never hardcoded into POS or procurement code.",
                        ],
                        [
                            'Period lock',
                            'A closed fiscal year blocks new postings unless someone has opened an approved reopen window.',
                        ],
                        ['Audit trail', 'Every meaningful accounting action is logged — who did what, and when.'],
                    ],
                },
            ],
        },
        {
            id: 's2',
            num: '2',
            title: 'Glossary',
            menu: null,
            intro:
                'Plain-English definitions for every term used in this guide. Search the sidebar for a word if you just need one definition.',
            blocks: [
                {
                    type: 'glossary',
                    title: 'General ledger',
                    terms: [
                        ['GL / General Ledger', 'The complete record of every account and every posted journal line in the system.'],
                        ['Chart of Accounts (COA)', 'Your master list of accounts — codes, names, types, and hierarchy.'],
                        ['Account code', 'A unique ID for an account, e.g. 1100 for Cash or 4100 for Sales Revenue.'],
                        ['Account type', 'Asset, Liability, Equity, Revenue, or Expense — decides whether it shows on the Balance Sheet or the P&amp;L.'],
                        ['Group account', "A header account used for organizing the hierarchy. Can't receive postings directly."],
                        ['Postable account', 'A real, leaf-level account that journal lines can actually be posted to.'],
                        ['Journal entry / voucher (JV)', 'A header — date, description, status — plus one or more journal lines.'],
                        ['Journal line / transaction', 'One row inside a journal: an account, a debit or a credit, and optional extra detail.'],
                        ['Debit', 'Increases assets and expenses; decreases liabilities, equity, and revenue.'],
                        ['Credit', 'The opposite of debit. Total debits must always equal total credits.'],
                        ['Draft journal', 'Created but not yet posted — still editable.'],
                        ['Posted journal', "Locked into the GL. Can't be changed — only reversed."],
                        ['Reversal', 'A system-made journal that exactly cancels out a posted one.'],
                        ['Opening balance', 'Your starting GL balances at go-live.'],
                        ['Closing entry', "The year-end journal that moves net income into retained earnings."],
                        ['Functional currency', "Your business's main reporting currency, set once in Financial Settings."],
                    ],
                },
                {
                    type: 'glossary',
                    title: 'Configuration layer',
                    terms: [
                        [
                            'Account mapping',
                            "A rule that says 'whenever the system needs account X, use this real GL account' — can vary by branch, warehouse, payment method, or currency.",
                        ],
                        ['Mapping key', 'The named thing being looked up, e.g. sales_revenue or accounts_receivable.'],
                        ['Posting rule set', 'A versioned recipe that turns one type of business event into journal lines.'],
                        ['Posting rule line', 'One line inside that recipe: debit or credit, where the amount comes from, and which account to use.'],
                        ['Amount source', 'Which number from the event to use — gross amount, tax amount, inventory cost, and so on.'],
                        ['Account resolution type', 'How the system finds the account — a fixed ID, a mapping lookup, the payment method used, a tax account, etc.'],
                        ['Accounting event', 'A record of something that happened in the business, waiting to become (or already turned into) a journal.'],
                        ['Idempotency key', 'A unique fingerprint that stops the same event from ever creating two journals.'],
                    ],
                },
                {
                    type: 'glossary',
                    title: 'Fiscal &amp; control',
                    terms: [
                        ['Fiscal year (FY)', 'A defined date range, e.g. 1 Jan – 31 Dec 2026, used for reporting and locking periods.'],
                        ['Fiscal year status', 'open, closing, closed, or reopening — controls whether posting is allowed.'],
                        ['Cutover date', 'The earliest date live, automatic posting is allowed.'],
                        ['Reopen window', 'A temporary window after a dual-approved reopen where posting to a closed year is allowed again — 48 hours by default.'],
                        ['Retained earnings', "The equity account that receives the year's profit or loss at close. Required before you can close a year."],
                    ],
                },
                {
                    type: 'glossary',
                    title: 'Tax',
                    terms: [
                        ['Tax type', 'A configured rate and method, e.g. GST 5%, linked to its own GL accounts.'],
                        ['Output tax', 'Tax you collect on sales — a liability.'],
                        ['Input tax', 'Tax you pay on purchases — an asset, or partly recoverable.'],
                        ['Recoverable percentage', 'On purchases, how much of the input tax you can actually claim back.'],
                        ['Exclusive tax', 'Tax added on top of the price (100 + 5% = 105).'],
                        ['Inclusive tax', 'Tax already baked into the price (105 inclusive of 5% tax = 100 net).'],
                    ],
                },
                {
                    type: 'glossary',
                    title: 'Sub-ledgers &amp; sub-modules',
                    terms: [
                        ['AR / Accounts Receivable', 'Money customers owe you.'],
                        ['AP / Accounts Payable', 'Money you owe suppliers.'],
                        ['Cost centre', 'An optional tag on a journal line for department or project-level profit and loss.'],
                        ['Bank reconciliation', "Matching your bank statement lines to what's actually posted in the GL."],
                        ['Petty cash', 'A small-cash register with top-ups and disbursements.'],
                        ['Cheque register', "Tracks a cheque's life from received/issued through cleared or bounced."],
                        ['Fixed asset', 'A capitalized item that depreciates over time.'],
                        ['Credit note', 'Reduces what a customer owes you. Fires the credit_note.issued event.'],
                        ['Debit note', 'Reduces what you owe a supplier. Issued from Procurement, not from Accounting.'],
                    ],
                },
                {
                    type: 'table',
                    title: 'Abbreviations',
                    headers: ['Short form', 'Means'],
                    rows: [
                        ['COA', 'Chart of Accounts'],
                        ['GL', 'General Ledger'],
                        ['JV', 'Journal Voucher / Journal Entry'],
                        ['FY', 'Fiscal Year'],
                        ['P&L', 'Profit and Loss (Income Statement)'],
                        ['TB', 'Trial Balance'],
                        ['GRN', 'Goods Receiving Note'],
                        ['COGS', 'Cost of Goods Sold'],
                        ['WAC', 'Weighted Average Cost'],
                        ['FIFO', 'First In, First Out'],
                        ['AR', 'Accounts Receivable'],
                        ['AP', 'Accounts Payable'],
                        ['FX', 'Foreign Exchange'],
                    ],
                },
            ],
        },
        {
            id: 's3',
            num: '3',
            title: 'The big picture',
            menu: null,
            intro:
                "Nothing in the point-of-sale screen hardcodes an account number. Instead, a business event flows through three layers before it ever touches your books.",
            blocks: [
                { type: 'flowdiagram' },
                {
                    type: 'steps',
                    title: 'How a sale actually becomes a journal',
                    items: [
                        'The POS finishes a sale and quietly announces <b>sale.completed</b>, carrying the amounts involved.',
                        "A <b>posting rule</b> for that event type says what to do: debit the payment account, credit sales revenue, credit output tax, debit COGS, credit inventory — whatever you've configured.",
                        '<b>Account mappings</b> translate those generic ideas into real account numbers — sales_revenue becomes 4100, output_tax becomes 2200, and so on.',
                        'The <b>Journal Service</b> checks the entry balances and the fiscal period is open, then posts it.',
                    ],
                },
                {
                    type: 'table',
                    title: 'The configuration stack, bottom to top',
                    headers: ['Layer', 'You configure', 'Effect'],
                    rows: [
                        ['1. Chart of Accounts', 'Account codes, types, hierarchy', 'Defines what accounts exist'],
                        ['2. Account Mappings', 'Keys → accounts, with optional scoping', 'Defines which account gets picked for each idea'],
                        ['3. Posting Rules', 'Event type → debit/credit lines', 'Defines how an event becomes a journal'],
                        ['4. Financial Settings', 'Currency, retained earnings, tax defaults, cutover', 'Sets the global defaults'],
                        ['5. Tax Types', 'Rates and their GL accounts', 'Stamps tax lines correctly'],
                        ['6. Fiscal Years', 'Open / close / reopen', 'Decides when posting is allowed at all'],
                    ],
                },
                {
                    type: 'note',
                    tone: 'warn',
                    text: "If layers 1–3 are incomplete, auto-posting either <b>fails</b> or is <b>skipped</b> — see Section 12 for exactly which.",
                },
                {
                    type: 'table',
                    title: "What you do → what you'll see",
                    headers: ['You do this…', 'System records…', "You'll find it in…"],
                    rows: [
                        ['Complete a POS sale', 'sale.completed → revenue, tax, cash/card, COGS', 'Journal Entries, GL, P&L, Trial Balance'],
                        ['Receive goods (GRN)', 'purchase.received → inventory + clearing', 'Journal Entries, Inventory Valuation'],
                        ['Match a supplier invoice', 'purchase.invoice_posted → AP, input tax', 'AP Aging, Journal Entries'],
                        ['Pay a supplier', 'payment.made → bank + AP', 'Bank Book, AP Aging'],
                        ['Issue a credit note', 'credit_note.issued → reduces AR', 'Credit Notes list, AR Aging'],
                        ['Adjust inventory', 'inventory.adjusted / stock.scrapped', 'Journal Entries, Inventory Valuation'],
                        ['Receive a stock transfer', 'transfer.confirmed', 'Inter-warehouse inventory GL (if configured)'],
                        ['Close a fiscal year', 'Closing journal → retained earnings', 'Fiscal year status flips to Closed'],
                        ['Import opening balances', 'Opening journal, after approval', 'Trial Balance, Balance Sheet at cutover'],
                    ],
                },
                {
                    type: 'note',
                    tone: 'info',
                    text: "<b>Posting happens in real time.</b> There's no overnight batch — auto-posting runs in the same moment as the sale or payment. If it fails, the sale itself still completes; you'll just see a Failed row on the Accounting Events screen to fix and retry.",
                },
            ],
        },

        ...(accountingGuideExtraSections as any),
    ] as any[],
};

export type AccountingGuide = typeof accountingGuide;

