import { applyCsrfHeaders, ensureCsrfCookie } from '@/lib/csrf';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/Components/ui/button';

type GuideKey = 'accounting' | 'customers-loyalty' | 'inventory-catalogue' | 'put-product-in-stock';

type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
};

function uid() {
    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function defaultSuggestions(t: (k: string) => string) {
    return [
        t('pages.helpSupport.askAi.suggestions.summarize'),
        t('pages.helpSupport.askAi.suggestions.howToAddProducts'),
        t('pages.helpSupport.askAi.suggestions.troubleshoot'),
    ];
}

function parseSseData(raw: string): string {
    const trimmed = raw.trim();
    if (!trimmed) return '';
    if (trimmed === '[DONE]') return '';

    try {
        const asJson = JSON.parse(trimmed);
        if (typeof asJson?.text === 'string') return asJson.text;
        if (typeof asJson?.delta === 'string') return asJson.delta;
        if (typeof asJson?.content === 'string') return asJson.content;
        if (typeof asJson?.message === 'string') return asJson.message;
    } catch {
        // ignore
    }

    return trimmed;
}

export default function GuideAskAiPanel({ guideKey }: { guideKey: GuideKey }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [input, setInput] = useState('');
    const [streaming, setStreaming] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const listRef = useRef<HTMLDivElement | null>(null);

    const suggestions = useMemo(() => defaultSuggestions(t), [t]);

    useEffect(() => {
        if (!open) return;
        requestAnimationFrame(() => {
            listRef.current?.scrollTo({ top: listRef.current.scrollHeight, behavior: 'smooth' });
        });
    }, [open, messages.length]);

    async function send(question: string) {
        const msg = question.trim();
        if (!msg || streaming) return;

        setError(null);
        setStreaming(true);

        const userMessage: ChatMessage = { id: uid(), role: 'user', content: msg };
        const assistantMessage: ChatMessage = { id: uid(), role: 'assistant', content: '' };

        setMessages((prev) => [...prev, userMessage, assistantMessage]);
        setInput('');

        try {
            await ensureCsrfCookie();

            const headers = applyCsrfHeaders(
                new Headers({
                    Accept: 'text/event-stream, application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }),
            );

            const history = [...messages, userMessage]
                .filter((m) => m.role === 'user' || m.role === 'assistant')
                .slice(-8)
                .map((m) => ({ role: m.role, content: m.content }));

            const res = await fetch(`/help-support/guides/${guideKey}/ask`, {
                method: 'POST',
                headers,
                body: JSON.stringify({ message: msg, history }),
                credentials: 'same-origin',
            });

            const contentType = res.headers.get('content-type') ?? '';

            // If we got redirected (e.g. to login), the browser will follow and we end up with HTML.
            if (res.redirected || (contentType.includes('text/html') && [401, 403, 419].includes(res.status))) {
                throw new Error(t('pages.helpSupport.askAi.errors.sessionExpired'));
            }

            // We only stream when the server actually returned SSE.
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

                // SSE frames are separated by blank lines.
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
                        <div className="flex items-center justify-between border-b border-[color:var(--g-border-soft)] px-4 py-3">
                            <div>
                                <div className="text-[12px] font-semibold text-[color:var(--g-teal)]">
                                    {t('pages.helpSupport.askAi.title')}
                                </div>
                                <div className="text-[11.5px] text-[color:var(--g-text-faint)]">
                                    {t('pages.helpSupport.askAi.subtitle')}
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setMessages([])}
                                    disabled={streaming}
                                >
                                    {t('pages.helpSupport.askAi.clear')}
                                </Button>
                                <Button type="button" variant="ghost" size="sm" onClick={() => setOpen(false)}>
                                    {t('pages.helpSupport.askAi.close')}
                                </Button>
                            </div>
                        </div>

                        <div ref={listRef} className="flex-1 space-y-3 overflow-y-auto px-4 py-4">
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

                                    <div className="flex flex-wrap gap-2">
                                        {suggestions.map((s) => (
                                            <button
                                                key={s}
                                                type="button"
                                                onClick={() => {
                                                    setOpen(true);
                                                    void send(s);
                                                }}
                                                className="rounded-full border border-[color:var(--g-border)] bg-[color:var(--g-panel)] px-3 py-1.5 text-[12px] font-medium text-[color:var(--g-text-dim)] hover:bg-[color:var(--g-panel-2)]"
                                            >
                                                {s}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            ) : null}

                            {messages.map((m) => (
                                <div
                                    key={m.id}
                                    className={
                                        m.role === 'user'
                                            ? 'ml-auto w-[92%] rounded-[12px] border border-[color:var(--g-border)] bg-[color:var(--g-panel-2)] p-3 text-[13px] text-[color:var(--g-text)]'
                                            : 'mr-auto w-[92%] rounded-[12px] border border-[color:var(--g-border-soft)] bg-[color:var(--g-panel)] p-3 text-[13px] text-[color:var(--g-text-dim)]'
                                    }
                                >
                                    {m.role === 'assistant' ? (
                                        <div className="prose prose-invert max-w-none prose-p:my-2 prose-li:my-1">
                                            <ReactMarkdown remarkPlugins={[remarkGfm]}>
                                                {m.content || (streaming ? t('pages.helpSupport.askAi.streaming') : '')}
                                            </ReactMarkdown>
                                        </div>
                                    ) : (
                                        <div className="whitespace-pre-wrap">{m.content}</div>
                                    )}
                                </div>
                            ))}

                            {error ? (
                                <div className="rounded-[10px] border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-[12px] text-rose-200">
                                    {error}
                                </div>
                            ) : null}
                        </div>

                        <form
                            className="border-t border-[color:var(--g-border-soft)] p-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                void send(input);
                            }}
                        >
                            <div className="flex items-end gap-2">
                                <textarea
                                    value={input}
                                    onChange={(e) => setInput(e.target.value)}
                                    rows={2}
                                    placeholder={t('pages.helpSupport.askAi.placeholder')}
                                    className="min-h-[42px] flex-1 resize-none rounded-[10px] border border-[color:var(--g-border)] bg-[color:var(--g-panel-2)] px-3 py-2 text-[13px] text-[color:var(--g-text)] outline-none placeholder:text-[color:var(--g-text-faint)] focus:border-[color:var(--g-teal-dim)]"
                                    disabled={streaming}
                                />
                                <Button type="submit" disabled={streaming || !input.trim()}>
                                    {t('pages.helpSupport.askAi.send')}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}
        </>
    );
}

