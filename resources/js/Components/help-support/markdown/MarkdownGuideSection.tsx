import { cn } from '@/lib/utils';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

function ExternalLinkIcon() {
    return (
        <span className="ml-1 inline-block align-baseline opacity-70">
            ↗
        </span>
    );
}

export default function MarkdownGuideSection({
    id,
    num,
    title,
    markdown,
    prev,
    next,
}: {
    id: string;
    num: string;
    title: string;
    markdown: string;
    prev?: { id: string; num: string; title: string };
    next?: { id: string; num: string; title: string };
}) {
    return (
        <section id={id} className="pt-16 [scroll-margin-top:90px]">
            <div className="flex items-baseline gap-3">
                <span className="rounded-md border border-[color:var(--g-teal-dim)] bg-[color:var(--g-teal-wash)] px-2 py-0.5 font-mono text-[13px] text-[color:var(--g-teal)]">
                    {num}
                </span>
                <h2 className="font-display text-[28px] font-normal">{title}</h2>
            </div>

            <div className="mt-5 space-y-4 text-[15px] leading-relaxed text-[color:var(--g-text-dim)]">
                <ReactMarkdown
                    remarkPlugins={[remarkGfm]}
                    components={{
                        h2: () => null, // title handled by layout above
                        h3: ({ children }) => (
                            <h3 className="mt-8 font-display text-[20px] font-normal text-[color:var(--g-text)]">
                                {children}
                            </h3>
                        ),
                        p: ({ children }) => <p className="max-w-[80ch]">{children}</p>,
                        a: ({ href, children }) => {
                            const isExternal = href?.startsWith('http');
                            return (
                                <a
                                    href={href}
                                    className="text-[color:var(--g-teal)] hover:underline"
                                    target={isExternal ? '_blank' : undefined}
                                    rel={isExternal ? 'noreferrer' : undefined}
                                >
                                    {children}
                                    {isExternal ? <ExternalLinkIcon /> : null}
                                </a>
                            );
                        },
                        code: ({ inline, children }) => (
                            <code
                                className={cn(
                                    'font-mono text-[0.9em]',
                                    inline
                                        ? 'rounded-md border border-[color:var(--g-border)] bg-[color:var(--g-panel)] px-1.5 py-0.5 text-[color:var(--g-teal)]'
                                        : 'block overflow-x-auto rounded-[10px] border border-[color:var(--g-border)] bg-[color:var(--g-panel)] p-4 text-[13px] text-[color:var(--g-text)]',
                                )}
                            >
                                {children}
                            </code>
                        ),
                        blockquote: ({ children }) => (
                            <div className="rounded-lg border border-[color:var(--g-border)] border-l-[3px] border-l-[color:var(--g-teal-dim)] bg-[color:var(--g-teal-wash)] px-4 py-3 text-[13.3px]">
                                {children}
                            </div>
                        ),
                        table: ({ children }) => (
                            <div className="my-6 overflow-hidden rounded-[10px] border border-[color:var(--g-border)] bg-[color:var(--g-panel)]">
                                <table className="w-full border-collapse text-[13.2px]">{children}</table>
                            </div>
                        ),
                        thead: ({ children }) => (
                            <thead className="bg-[color:var(--g-panel-2)]">{children}</thead>
                        ),
                        th: ({ children }) => (
                            <th className="border-b border-[color:var(--g-border)] px-3.5 py-2.5 text-left text-[11px] font-semibold tracking-[0.4px] text-[color:var(--g-text-faint)] uppercase">
                                {children}
                            </th>
                        ),
                        td: ({ children }) => (
                            <td className="border-b border-[color:var(--g-border-soft)] px-3.5 py-2.5 align-top">
                                {children}
                            </td>
                        ),
                        ul: ({ children }) => (
                            <ul className="ml-5 list-disc space-y-1">{children}</ul>
                        ),
                        ol: ({ children }) => (
                            <ol className="ml-5 list-decimal space-y-1">{children}</ol>
                        ),
                        hr: () => (
                            <hr className="my-8 border-0 border-t border-[color:var(--g-border-soft)]" />
                        ),
                    }}
                >
                    {markdown}
                </ReactMarkdown>
            </div>

            <div className="mt-12 flex gap-4 border-t border-[color:var(--g-border-soft)] pt-6">
                {prev ? (
                    <a
                        href={`#${prev.id}`}
                        className="flex-1 rounded-[10px] border border-[color:var(--g-border)] bg-[color:var(--g-panel)] px-4 py-3 text-[color:var(--g-text-dim)] transition hover:-translate-y-0.5 hover:border-[color:var(--g-teal-dim)] no-underline"
                    >
                        <div className="text-[10.5px] tracking-[0.5px] text-[color:var(--g-text-faint)] uppercase">
                            Previous
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
                        <div className="text-[10.5px] tracking-[0.5px] text-[color:var(--g-text-faint)] uppercase">
                            Next
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

