import { cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight } from 'lucide-react';

type Block =
    | { type: 'table'; title?: string; headers: string[]; rows: string[][] }
    | { type: 'steps'; title?: string; items: string[] }
    | { type: 'note'; tone?: 'info' | 'warn' | 'danger'; text: string }
    | { type: 'whatif'; title?: string; items: Array<{ cause: string; effect: string }> }
    | { type: 'glossary'; title?: string; terms: Array<[string, string]> }
    | { type: 'flow' ; title?: string; steps: string[] }
    | { type: 'flowdiagram' };

export type GuideSectionData = {
    id: string;
    num: string;
    title: string;
    menu?: string | null;
    intro: string;
    blocks: Block[];
};

function BlockTitle({ title }: { title?: string }) {
    if (!title) return null;
    return (
        <div className="mb-3 flex items-center gap-2 text-[13px] font-bold text-[color:var(--g-text)]">
            <span className="h-1.5 w-1.5 rounded-full bg-[color:var(--g-teal)]" />
            <span dangerouslySetInnerHTML={{ __html: title }} />
        </div>
    );
}

function Rich({ html }: { html: string }) {
    return <span dangerouslySetInnerHTML={{ __html: html }} />;
}

function TableBlock({ b }: { b: Extract<Block, { type: 'table' }> }) {
    return (
        <div className="my-6">
            <BlockTitle title={b.title} />
            <div className="overflow-hidden rounded-[10px] border border-[color:var(--g-border)] bg-[color:var(--g-panel)]">
                <table className="w-full border-collapse text-[13.2px]">
                    <thead>
                        <tr className="bg-[color:var(--g-panel-2)]">
                            {b.headers.map((h) => (
                                <th
                                    key={h}
                                    className="border-b border-[color:var(--g-border)] px-3.5 py-2.5 text-left text-[11px] font-semibold tracking-[0.4px] text-[color:var(--g-text-faint)] uppercase"
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {b.rows.map((r, idx) => (
                            <tr key={idx} className="hover:bg-white/[0.015]">
                                {r.map((c, cidx) => (
                                    <td
                                        key={cidx}
                                        className={cn(
                                            'border-b border-[color:var(--g-border-soft)] px-3.5 py-2.5 align-top text-[color:var(--g-text-dim)]',
                                            cidx === 0 && 'font-medium text-[color:var(--g-text)]',
                                            idx === b.rows.length - 1 && 'border-b-0',
                                        )}
                                    >
                                        <Rich html={c} />
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function StepsBlock({ b }: { b: Extract<Block, { type: 'steps' }> }) {
    return (
        <div className="my-6">
            <BlockTitle title={b.title} />
            <div className="flex flex-col">
                {b.items.map((t, i) => (
                    <div key={i} className="relative flex gap-4 pb-5 last:pb-0">
                        {i < b.items.length - 1 && (
                            <div className="absolute top-7 left-[13px] bottom-0 w-px bg-[color:var(--g-border)]" />
                        )}
                        <div className="z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-[color:var(--g-teal-dim)] bg-[color:var(--g-teal-wash)] font-mono text-[12px] text-[color:var(--g-teal)]">
                            {i + 1}
                        </div>
                        <div className="pt-0.5 text-[13.6px] leading-relaxed text-[color:var(--g-text-dim)]">
                            <Rich html={t} />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function NoteBlock({ b }: { b: Extract<Block, { type: 'note' }> }) {
    const tone = b.tone ?? 'info';
    const borderLeft =
        tone === 'warn'
            ? 'border-l-[color:var(--g-amber)] bg-[color:var(--g-amber-wash)]'
            : tone === 'danger'
                ? 'border-l-[color:var(--g-red)] bg-[color:var(--g-red-wash)]'
                : 'border-l-[color:var(--g-teal-dim)] bg-[color:var(--g-teal-wash)]';

    return (
        <div className="my-6">
            <div
                className={cn(
                    'flex gap-3 rounded-lg border border-[color:var(--g-border)] border-l-[3px] px-4 py-3 text-[13.3px] text-[color:var(--g-text-dim)]',
                    borderLeft,
                )}
            >
                <div className="mt-0.5 h-4 w-4 shrink-0 rounded-full bg-[color:var(--g-text-faint)]/20" />
                <div>
                    <Rich html={b.text} />
                </div>
            </div>
        </div>
    );
}

function WhatIfBlock({ b }: { b: Extract<Block, { type: 'whatif' }> }) {
    return (
        <div className="my-6">
            <BlockTitle title={b.title} />
            <div className="flex flex-col gap-2">
                {b.items.map((it, idx) => (
                    <details
                        key={idx}
                        className="overflow-hidden rounded-[9px] border border-[color:var(--g-border)] bg-[color:var(--g-panel)]"
                    >
                        <summary className="flex cursor-pointer list-none items-center gap-3 px-3.5 py-3 text-[13.4px]">
                            <ChevronRight className="h-4 w-4 text-[color:var(--g-text-faint)] transition group-open:rotate-90 group-open:text-[color:var(--g-teal)]" />
                            <span className="flex-1 font-medium text-[color:var(--g-text)]">
                                <Rich html={it.cause} />
                            </span>
                        </summary>
                        <div className="flex gap-2 px-3.5 pb-3 pl-10 text-[13px] leading-relaxed text-[color:var(--g-text-dim)]">
                            <span className="mt-1 h-3.5 w-3.5 shrink-0 rounded-full bg-[color:var(--g-teal)]/20" />
                            <div>
                                <Rich html={it.effect} />
                            </div>
                        </div>
                    </details>
                ))}
            </div>
        </div>
    );
}

function GlossaryBlock({ b }: { b: Extract<Block, { type: 'glossary' }> }) {
    return (
        <div className="my-6">
            <BlockTitle title={b.title} />
            <dl className="grid grid-cols-1 gap-0">
                {b.terms.map(([term, def]) => (
                    <div
                        key={term}
                        className="grid grid-cols-1 gap-1 border-b border-[color:var(--g-border-soft)] py-3 last:border-b-0 sm:grid-cols-[200px_1fr] sm:gap-4"
                    >
                        <dt className="text-[13.4px] font-semibold text-[color:var(--g-text)]">
                            <Rich html={term} />
                        </dt>
                        <dd className="m-0 text-[13.2px] leading-relaxed text-[color:var(--g-text-dim)]">
                            <Rich html={def} />
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

function FlowBlock({ b }: { b: Extract<Block, { type: 'flow' }> }) {
    return (
        <div className="my-6">
            <BlockTitle title={b.title} />
            <div className="flex flex-wrap items-center gap-2 rounded-lg border border-[color:var(--g-border)] bg-[color:var(--g-panel)] px-3.5 py-3 text-[12.8px]">
                {b.steps.map((s, i) => (
                    <span key={`${s}-${i}`} className="inline-flex items-center gap-2">
                        <span className="rounded-md border border-[color:var(--g-border)] bg-[color:var(--g-panel-2)] px-2.5 py-1 font-mono text-[11.5px] text-[color:var(--g-text-dim)]">
                            {s}
                        </span>
                        {i < b.steps.length - 1 ? (
                            <ChevronRight className="h-4 w-4 text-[color:var(--g-text-faint)]" />
                        ) : null}
                    </span>
                ))}
            </div>
        </div>
    );
}

function FlowDiagramBlock() {
    const rows: Array<[string, string]> = [
        ['sale.completed', 'Dr Cash/Card · Cr Revenue · Cr Output tax · Dr COGS · Cr Inventory'],
        ['purchase.received', 'Dr Inventory · Cr Clearing/AP'],
        ['payment.made', 'Dr AP · Cr Bank'],
        ['inventory.adjusted', 'Dr/Cr Inventory vs. adjustment expense'],
    ];

    return (
        <div className="my-6">
            <div className="mb-3 flex items-center gap-2 text-[13px] font-bold text-[color:var(--g-text)]">
                <span className="h-1.5 w-1.5 rounded-full bg-[color:var(--g-teal)]" />
                A few real events, traced end to end
            </div>

            <div className="space-y-2">
                {rows.map(([ev, impact]) => (
                    <div
                        key={ev}
                        className="flex flex-wrap items-center gap-2 rounded-lg border border-[color:var(--g-border)] bg-[color:var(--g-panel)] px-3.5 py-3 text-[12.8px]"
                    >
                        <span className="rounded-md border border-[color:var(--g-teal-dim)] bg-[color:var(--g-panel-2)] px-2.5 py-1 font-mono text-[11.5px] text-[color:var(--g-teal)]">
                            {ev}
                        </span>
                        <ChevronRight className="h-4 w-4 text-[color:var(--g-text-faint)]" />
                        <span className="rounded-md border border-[color:var(--g-border)] bg-[color:var(--g-panel-2)] px-2.5 py-1 font-mono text-[11.5px] text-[color:var(--g-text-dim)]">
                            Posting Rules
                        </span>
                        <ChevronRight className="h-4 w-4 text-[color:var(--g-text-faint)]" />
                        <span className="rounded-md border border-[color:var(--g-border)] bg-[color:var(--g-panel-2)] px-2.5 py-1 font-mono text-[11.5px] text-[color:var(--g-text-dim)]">
                            Account Mappings
                        </span>
                        <ChevronRight className="h-4 w-4 text-[color:var(--g-text-faint)]" />
                        <span className="rounded-md border border-[color:var(--g-border)] bg-[color:var(--g-panel-2)] px-2.5 py-1 font-mono text-[11.5px] text-[color:var(--g-text-dim)]">
                            Chart of Accounts
                        </span>
                        <ChevronRight className="h-4 w-4 text-[color:var(--g-text-faint)]" />
                        <span className="min-w-0 flex-1 text-[color:var(--g-text-dim)]">
                            {impact}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function GuideSection({
    section,
    prev,
    next,
}: {
    section: GuideSectionData;
    prev?: GuideSectionData;
    next?: GuideSectionData;
}) {
    return (
        <section id={section.id} className="pt-16 [scroll-margin-top:90px]">
            <div className="flex items-baseline gap-3">
                <span className="rounded-md border border-[color:var(--g-teal-dim)] bg-[color:var(--g-teal-wash)] px-2 py-0.5 font-mono text-[13px] text-[color:var(--g-teal)]">
                    {section.num}
                </span>
                <h2 className="font-display text-[28px] font-normal">{section.title}</h2>
            </div>

            <p className="mt-3 max-w-[68ch] text-[15px] text-[color:var(--g-text-dim)]">
                <Rich html={section.intro} />
            </p>

            {section.menu ? (
                <div className="mt-4 inline-flex items-center gap-2 rounded-full border border-[color:var(--g-border)] bg-[color:var(--g-panel)] px-3 py-1.5 text-[12px] text-[color:var(--g-text-dim)]">
                    <ChevronRight className="h-3.5 w-3.5 opacity-70" />
                    {section.menu}
                </div>
            ) : null}

            {section.blocks.map((b, idx) => {
                if (b.type === 'table') return <TableBlock key={idx} b={b} />;
                if (b.type === 'steps') return <StepsBlock key={idx} b={b} />;
                if (b.type === 'note') return <NoteBlock key={idx} b={b} />;
                if (b.type === 'whatif') return <WhatIfBlock key={idx} b={b} />;
                if (b.type === 'glossary') return <GlossaryBlock key={idx} b={b} />;
                if (b.type === 'flow') return <FlowBlock key={idx} b={b} />;
                if (b.type === 'flowdiagram') return <FlowDiagramBlock key={idx} />;
                return null;
            })}

            <div className="mt-12 flex gap-4 border-t border-[color:var(--g-border-soft)] pt-6">
                {prev ? (
                    <a
                        href={`#${prev.id}`}
                        className="flex-1 rounded-[10px] border border-[color:var(--g-border)] bg-[color:var(--g-panel)] px-4 py-3 text-[color:var(--g-text-dim)] transition hover:-translate-y-0.5 hover:border-[color:var(--g-teal-dim)] no-underline"
                    >
                        <div className="flex items-center gap-2 text-[10.5px] tracking-[0.5px] text-[color:var(--g-text-faint)] uppercase">
                            <ChevronLeft className="h-4 w-4" /> Previous
                        </div>
                        <div className="mt-1 text-[14px] font-semibold text-[color:var(--g-text)]">
                            {prev.num}. {prev.title}
                        </div>
                    </a>
                ) : (
                    <span className="flex-1" />
                )}

                {next ? (
                    <a
                        href={`#${next.id}`}
                        className="ml-auto flex-1 text-right rounded-[10px] border border-[color:var(--g-border)] bg-[color:var(--g-panel)] px-4 py-3 text-[color:var(--g-text-dim)] transition hover:-translate-y-0.5 hover:border-[color:var(--g-teal-dim)] no-underline"
                    >
                        <div className="flex items-center justify-end gap-2 text-[10.5px] tracking-[0.5px] text-[color:var(--g-text-faint)] uppercase">
                            Next <ChevronRight className="h-4 w-4" />
                        </div>
                        <div className="mt-1 text-[14px] font-semibold text-[color:var(--g-text)]">
                            {next.num}. {next.title}
                        </div>
                    </a>
                ) : null}
            </div>
        </section>
    );
}

