import { applyCsrfHeaders, ensureCsrfCookie } from '@/lib/csrf';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { ArrowUp, Copy, MoreHorizontal, Trash2, X } from 'lucide-react';
import ScrollArea from '@/Components/common/ScrollArea';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    buildGuideQuestions,
    filterTypeaheadSuggestions,
    pickRotatingSuggestions,
    type GuideKey,
} from '@/Components/help-support/guideAskSuggestions';

type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
};

type SectionLike = {
    title?: string | null;
    menu?: string | null;
};

function uid() {
    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

/** Laravel AI SSE payloads are JSON StreamEvents. Only text deltas belong in the chat bubble. */
function parseSseData(raw: string): string {
    const trimmed = raw.trim();
    if (!trimmed || trimmed === '[DONE]') return '';

    try {
        const event = JSON.parse(trimmed) as Record<string, unknown>;
        if (!event || typeof event !== 'object') return '';

        const type = typeof event.type === 'string' ? event.type : '';

        if (type === 'text_delta' || type === 'text-delta') {
            return typeof event.delta === 'string' ? event.delta : '';
        }

        if (
            type === 'stream_start' ||
            type === 'stream_end' ||
            type === 'text_start' ||
            type === 'text_end' ||
            type === 'start' ||
            type === 'finish' ||
            type === 'reasoning_delta' ||
            type === 'error' ||
            type.startsWith('tool_') ||
            type.startsWith('provider_')
        ) {
            return '';
        }

        if (type) return '';

        if (typeof event.delta === 'string') return event.delta;
        if (typeof event.text === 'string') return event.text;
        if (typeof event.content === 'string') return event.content;

        return '';
    } catch {
        if (
            trimmed.startsWith('<!DOCTYPE') ||
            trimmed.startsWith('<html') ||
            trimmed.includes('Ignition') ||
            (trimmed.startsWith('{') && trimmed.includes('"type"'))
        ) {
            return '';
        }

        return trimmed;
    }
}

function formatChatTranscript(messages: ChatMessage[]): string {
    return messages
        .filter((m) => m.content.trim() !== '')
        .map((m) => `${m.role === 'user' ? 'You' : 'Guide Assistant'}:\n${m.content.trim()}`)
        .join('\n\n');
}

/** Sync copy that works on http://*.test (Clipboard API is HTTPS-only). */
function copyTextToClipboard(text: string): boolean {
    const selection = window.getSelection();
    const previousRanges: Range[] = [];
    if (selection) {
        for (let i = 0; i < selection.rangeCount; i += 1) {
            previousRanges.push(selection.getRangeAt(i));
        }
    }

    const el = document.createElement('div');
    el.textContent = text;
    el.setAttribute('contenteditable', 'true');
    el.style.cssText =
        'position:fixed;inset:0;width:2em;height:2em;padding:0;border:0;outline:none;box-shadow:none;background:transparent;opacity:0;z-index:-1;white-space:pre-wrap;';
    document.body.appendChild(el);

    try {
        const range = document.createRange();
        range.selectNodeContents(el);
        selection?.removeAllRanges();
        selection?.addRange(range);
        el.focus();

        const ok = document.execCommand('copy');
        return Boolean(ok);
    } catch {
        return false;
    } finally {
        document.body.removeChild(el);
        selection?.removeAllRanges();
        for (const range of previousRanges) {
            try {
                selection?.addRange(range);
            } catch {
                // ignore
            }
        }
    }
}

export default function GuideAskAiPanel({
    guideKey,
    sections = [],
}: {
    guideKey: GuideKey;
    sections?: SectionLike[];
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [input, setInput] = useState('');
    const [streaming, setStreaming] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [rotation, setRotation] = useState(0);
    const [typeaheadOpen, setTypeaheadOpen] = useState(false);

    const listRef = useRef<HTMLDivElement | null>(null);
    const inputRef = useRef<HTMLTextAreaElement | null>(null);

    const questionBank = useMemo(() => buildGuideQuestions(guideKey, sections), [guideKey, sections]);
    const sampleQuestions = useMemo(
        () => pickRotatingSuggestions(questionBank, rotation, 3),
        [questionBank, rotation],
    );
    const typeahead = useMemo(
        () => filterTypeaheadSuggestions(questionBank, input, 5),
        [questionBank, input],
    );

    const lastContent = messages.at(-1)?.content ?? '';

    useEffect(() => {
        if (!open) return;
        requestAnimationFrame(() => {
            listRef.current?.scrollTo({ top: listRef.current.scrollHeight, behavior: 'smooth' });
        });
    }, [open, messages.length, lastContent]);

    useEffect(() => {
        if (!open || messages.length > 0 || questionBank.length <= 3) return;

        const id = window.setInterval(() => {
            setRotation((n) => n + 1);
        }, 8000);

        return () => window.clearInterval(id);
    }, [open, messages.length, questionBank.length]);

    function copyChat() {
        const text = formatChatTranscript(messages);
        if (!text) {
            toast.message(t('pages.helpSupport.askAi.copyEmpty'));
            return;
        }

        if (copyTextToClipboard(text)) {
            toast.success(t('pages.helpSupport.askAi.copied'));
            return;
        }

        toast.error(t('pages.helpSupport.askAi.errors.generic'));
    }

    function copyMessage(content: string) {
        const text = content.trim();
        if (!text) {
            toast.message(t('pages.helpSupport.askAi.copyEmpty'));
            return;
        }

        if (copyTextToClipboard(text)) {
            toast.success(t('pages.helpSupport.askAi.messageCopied'));
            return;
        }

        toast.error(t('pages.helpSupport.askAi.errors.generic'));
    }

    function clearChat() {
        if (streaming) return;
        setMessages([]);
        setError(null);
        setInput('');
        setTypeaheadOpen(false);
    }

    async function send(question: string) {
        const msg = question.trim();
        if (!msg || streaming) return;

        setError(null);
        setStreaming(true);
        setTypeaheadOpen(false);

        const userMessage: ChatMessage = { id: uid(), role: 'user', content: msg };
        const assistantMessage: ChatMessage = { id: uid(), role: 'assistant', content: '' };

        setMessages((prev) => [...prev, userMessage, assistantMessage]);
        setInput('');

        try {
            await ensureCsrfCookie();

            const headers = applyCsrfHeaders(
                new Headers({
                    Accept: 'application/json, text/event-stream',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }),
            );

            const history = messages
                .filter((m) => (m.role === 'user' || m.role === 'assistant') && m.content.trim() !== '')
                .slice(-8)
                .map((m) => ({
                    role: m.role,
                    content: m.content.length > 8000 ? `${m.content.slice(0, 8000)}…` : m.content,
                }));

            const res = await fetch(`/help-support/guides/${guideKey}/ask`, {
                method: 'POST',
                headers,
                body: JSON.stringify({ message: msg, history }),
                credentials: 'same-origin',
            });

            const contentType = res.headers.get('content-type') ?? '';

            if (res.redirected || (contentType.includes('text/html') && [401, 403, 419].includes(res.status))) {
                throw new Error(t('pages.helpSupport.askAi.errors.sessionExpired'));
            }

            if (!contentType.includes('text/event-stream')) {
                let serverMessage = '';
                try {
                    if (contentType.includes('application/json')) {
                        const json = await res.json();
                        serverMessage = typeof json?.message === 'string' ? json.message : '';
                    } else {
                        serverMessage = (await res.text()).trim();
                    }
                } catch {
                    // ignore
                }

                throw new Error(
                    serverMessage
                        ? serverMessage
                        : `${t('pages.helpSupport.askAi.errors.http')} (${res.status})`,
                );
            }

            if (!res.ok || !res.body) {
                let serverMessage = '';
                try {
                    if (contentType.includes('application/json')) {
                        const json = await res.json();
                        serverMessage = typeof json?.message === 'string' ? json.message : '';
                    } else {
                        serverMessage = (await res.text()).trim();
                    }
                } catch {
                    // ignore
                }

                throw new Error(
                    serverMessage
                        ? serverMessage
                        : `${t('pages.helpSupport.askAi.errors.http')} (${res.status})`,
                );
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let buffer = '';

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });

                const parts = buffer.split('\n\n');
                buffer = parts.pop() ?? '';

                for (const part of parts) {
                    const lines = part.split('\n');
                    for (const line of lines) {
                        if (!line.startsWith('data:')) continue;
                        const chunk = parseSseData(line.slice('data:'.length));
                        if (!chunk) continue;

                        setMessages((prev) =>
                            prev.map((m) =>
                                m.id === assistantMessage.id
                                    ? { ...m, content: (m.content ?? '') + chunk }
                                    : m,
                            ),
                        );
                    }
                }
            }
        } catch (e: any) {
            setError(e?.message ?? t('pages.helpSupport.askAi.errors.generic'));
        } finally {
            setStreaming(false);
        }
    }

    function applySuggestion(q: string) {
        setInput(q);
        setTypeaheadOpen(false);
        void send(q);
    }

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="fixed right-6 bottom-6 z-40 flex h-12 items-center gap-2 rounded-full border border-[color:var(--g-teal-dim)] bg-[color:var(--g-panel)] px-4 text-[13px] font-semibold text-[color:var(--g-teal)] shadow-sm hover:bg-[color:var(--g-panel-2)]"
            >
                <span className="text-[16px] leading-none">✦</span>
                {t('pages.helpSupport.askAi.button')}
            </button>

            {open ? (
                <div className="fixed inset-0 z-50">
                    <button
                        type="button"
                        className="absolute inset-0 bg-black/40"
                        onClick={() => setOpen(false)}
                        aria-label={t('pages.helpSupport.askAi.close')}
                    />

                    <div className="absolute right-4 bottom-4 flex h-[78vh] w-[min(520px,calc(100vw-2rem))] flex-col overflow-hidden rounded-[14px] border border-[color:var(--g-border)] bg-[color:var(--g-panel)] shadow-xl">
                        <div className="flex items-start justify-between gap-3 border-b border-[color:var(--g-border-soft)] px-4 py-3">
                            <div className="min-w-0 pt-0.5">
                                <div className="text-[12px] font-semibold text-[color:var(--g-teal)]">
                                    {t('pages.helpSupport.askAi.title')}
                                </div>
                                <div className="text-[11.5px] text-[color:var(--g-text-faint)]">
                                    {t('pages.helpSupport.askAi.subtitle')}
                                </div>
                            </div>

                            <div className="flex shrink-0 items-center gap-0.5">
                                <button
                                    type="button"
                                    onClick={() => copyChat()}
                                    className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[color:var(--g-text-dim)] transition-colors hover:bg-[color:var(--g-panel-2)] hover:text-[color:var(--g-text)]"
                                    aria-label={t('pages.helpSupport.askAi.copyChat')}
                                    title={t('pages.helpSupport.askAi.copyChat')}
                                >
                                    <Copy className="h-4 w-4" />
                                </button>

                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            type="button"
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[color:var(--g-text-dim)] transition-colors hover:bg-[color:var(--g-panel-2)] hover:text-[color:var(--g-text)]"
                                            aria-label={t('pages.helpSupport.askAi.menu')}
                                            disabled={streaming}
                                        >
                                            <MoreHorizontal className="h-4 w-4" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="min-w-[10.5rem]">
                                        <DropdownMenuItem
                                            variant="destructive"
                                            disabled={streaming || messages.length === 0}
                                            onSelect={() => clearChat()}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                            {t('pages.helpSupport.askAi.clear')}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                <button
                                    type="button"
                                    onClick={() => setOpen(false)}
                                    className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[color:var(--g-text-dim)] transition-colors hover:bg-[color:var(--g-panel-2)] hover:text-[color:var(--g-text)]"
                                    aria-label={t('pages.helpSupport.askAi.close')}
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <ScrollArea ref={listRef} className="flex-1 space-y-3 overflow-y-auto px-4 py-4">
                            {messages.length === 0 ? (
                                <div className="space-y-3">
                                    <div className="rounded-[12px] border border-[color:var(--g-border-soft)] bg-[color:var(--g-panel-2)] p-4 text-[13px] text-[color:var(--g-text-dim)]">
                                        <div className="font-semibold text-[color:var(--g-text)]">
                                            {t('pages.helpSupport.askAi.emptyTitle')}
                                        </div>
                                        <div className="mt-1 leading-relaxed">
                                            {t('pages.helpSupport.askAi.emptyBody')}
                                        </div>
                                    </div>

                                    {sampleQuestions.length > 0 ? (
                                        <div className="space-y-2">
                                            <div className="text-[11px] font-semibold tracking-wide text-[color:var(--g-text-faint)] uppercase">
                                                {t('pages.helpSupport.askAi.tryAsking')}
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                {sampleQuestions.map((s) => (
                                                    <button
                                                        key={s}
                                                        type="button"
                                                        onClick={() => applySuggestion(s)}
                                                        className="rounded-full border border-[color:var(--g-border)] bg-[color:var(--g-panel)] px-3 py-1.5 text-left text-[12px] font-medium text-[color:var(--g-text-dim)] transition-colors hover:border-[color:var(--g-teal-dim)] hover:bg-[color:var(--g-panel-2)] hover:text-[color:var(--g-text)]"
                                                    >
                                                        {s}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    ) : null}
                                </div>
                            ) : null}

                            {messages.map((m) => {
                                const isUser = m.role === 'user';
                                const canCopy = m.content.trim() !== '';
                                const isStreamingReply =
                                    !isUser && !canCopy && streaming && m.id === messages.at(-1)?.id;

                                return (
                                    <div
                                        key={m.id}
                                        className={isUser ? 'ml-auto w-[92%]' : 'mr-auto w-[92%]'}
                                    >
                                        <div
                                            className={
                                                isUser
                                                    ? 'rounded-[12px] border border-[color:var(--g-border)] bg-[color:var(--g-panel-2)] p-3 text-[13px] text-[color:var(--g-text)]'
                                                    : 'rounded-[12px] border border-[color:var(--g-border-soft)] bg-[color:var(--g-panel)] p-3 text-[13px] text-[color:var(--g-text-dim)]'
                                            }
                                        >
                                            {!isUser ? (
                                                <div className="prose prose-invert max-w-none prose-headings:mb-2 prose-headings:mt-3 prose-headings:text-[color:var(--g-text)] prose-p:my-2 prose-p:leading-relaxed prose-li:my-0.5 prose-strong:text-[color:var(--g-text)]">
                                                    {canCopy ? (
                                                        <ReactMarkdown remarkPlugins={[remarkGfm]}>
                                                            {m.content}
                                                        </ReactMarkdown>
                                                    ) : isStreamingReply ? (
                                                        <span className="text-[color:var(--g-text-faint)]">
                                                            {t('pages.helpSupport.askAi.streaming')}
                                                        </span>
                                                    ) : null}
                                                </div>
                                            ) : (
                                                <div className="whitespace-pre-wrap">{m.content}</div>
                                            )}
                                        </div>

                                        {canCopy ? (
                                            <div
                                                className={
                                                    isUser
                                                        ? 'mt-1 flex justify-end'
                                                        : 'mt-1 flex justify-start'
                                                }
                                            >
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <button
                                                            type="button"
                                                            className="inline-flex h-7 w-7 items-center justify-center rounded-md text-[color:var(--g-text-faint)] transition-colors hover:bg-[color:var(--g-panel-2)] hover:text-[color:var(--g-text)]"
                                                            aria-label={t('pages.helpSupport.askAi.messageMenu')}
                                                        >
                                                            <MoreHorizontal className="h-3.5 w-3.5" />
                                                        </button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align={isUser ? 'end' : 'start'}
                                                        className="min-w-[8.5rem]"
                                                    >
                                                        <DropdownMenuItem
                                                            onPointerDown={(e) => {
                                                                // Sync copy during the gesture; menu focus breaks Clipboard API on http.
                                                                e.preventDefault();
                                                                copyMessage(m.content);
                                                            }}
                                                            onSelect={(e) => e.preventDefault()}
                                                        >
                                                            <Copy className="h-4 w-4" />
                                                            {t('pages.helpSupport.askAi.copyMessage')}
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        ) : null}
                                    </div>
                                );
                            })}

                            {error ? (
                                <div className="rounded-[10px] border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-[12px] text-rose-200">
                                    {error}
                                </div>
                            ) : null}
                        </ScrollArea>

                        <form
                            className="relative border-t border-[color:var(--g-border-soft)] p-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                void send(input);
                            }}
                        >
                            {typeaheadOpen && typeahead.length > 0 ? (
                                <div className="absolute right-3 bottom-[calc(100%-2px)] left-3 z-10 overflow-hidden rounded-[12px] border border-[color:var(--g-border)] bg-[color:var(--g-panel)] shadow-lg">
                                    <ul className="max-h-48 overflow-y-auto py-1">
                                        {typeahead.map((s) => (
                                            <li key={s}>
                                                <button
                                                    type="button"
                                                    className="flex w-full px-3 py-2 text-left text-[12.5px] text-[color:var(--g-text-dim)] hover:bg-[color:var(--g-panel-2)] hover:text-[color:var(--g-text)]"
                                                    onMouseDown={(e) => e.preventDefault()}
                                                    onClick={() => applySuggestion(s)}
                                                >
                                                    {s}
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ) : null}

                            <div className="flex items-end gap-2 rounded-[12px] border border-[color:var(--g-border)] bg-[color:var(--g-panel-2)] p-2 focus-within:border-[color:var(--g-teal-dim)]">
                                <textarea
                                    ref={inputRef}
                                    value={input}
                                    onChange={(e) => {
                                        setInput(e.target.value);
                                        setTypeaheadOpen(true);
                                    }}
                                    onFocus={() => setTypeaheadOpen(true)}
                                    onBlur={() => {
                                        window.setTimeout(() => setTypeaheadOpen(false), 120);
                                    }}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' && !e.shiftKey) {
                                            e.preventDefault();
                                            void send(input);
                                        }
                                        if (e.key === 'Escape') {
                                            setTypeaheadOpen(false);
                                        }
                                    }}
                                    rows={2}
                                    placeholder={t('pages.helpSupport.askAi.placeholder')}
                                    className="min-h-[42px] flex-1 resize-none bg-transparent px-2 py-1.5 text-[13px] text-[color:var(--g-text)] outline-none placeholder:text-[color:var(--g-text-faint)]"
                                    disabled={streaming}
                                />
                                <button
                                    type="submit"
                                    disabled={streaming || !input.trim()}
                                    className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[color:var(--g-teal)] text-[color:var(--g-panel)] transition-opacity disabled:cursor-not-allowed disabled:opacity-40"
                                    aria-label={t('pages.helpSupport.askAi.send')}
                                >
                                    <ArrowUp className="h-4 w-4" strokeWidth={2.5} />
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}
        </>
    );
}
