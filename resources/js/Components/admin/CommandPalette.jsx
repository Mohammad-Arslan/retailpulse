import ScrollArea from '@/Components/common/ScrollArea';
import { resolveNavIcon, withNavIcons } from '@/config/adminNav';
import { globalSearch } from '@/lib/searchApi';
import { cn } from '@/lib/utils';
import { router, usePage } from '@inertiajs/react';
import { Clock, Loader2, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

const RECENT_KEY = 'rp-global-search-recent';
const MAX_RECENTS = 8;

function loadRecents() {
    try {
        const raw = localStorage.getItem(RECENT_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed.slice(0, MAX_RECENTS) : [];
    } catch {
        return [];
    }
}

function saveRecent(entry) {
    if (!entry?.href || !entry?.title) {
        return;
    }
    const next = [
        entry,
        ...loadRecents().filter((r) => r.href !== entry.href && r.id !== entry.id),
    ].slice(0, MAX_RECENTS);
    try {
        localStorage.setItem(RECENT_KEY, JSON.stringify(next));
    } catch {
        /* ignore quota */
    }
}

function displayTitle(item, t) {
    if (item?.meta?.titleKey) {
        return t(`nav.${item.meta.titleKey}`, { defaultValue: item.title });
    }
    return item?.title ?? '';
}

function displaySubtitle(item, t) {
    if (item?.meta?.sectionKey) {
        return t(`nav.${item.meta.sectionKey}`, { defaultValue: item.subtitle });
    }
    return item?.subtitle ?? '';
}

function highlightMatch(text, query) {
    if (!text || !query?.trim()) {
        return text;
    }
    const q = query.trim();
    const idx = text.toLowerCase().indexOf(q.toLowerCase());
    if (idx < 0) {
        return text;
    }
    return (
        <>
            {text.slice(0, idx)}
            <mark className="rounded-sm bg-teal-100 px-0.5 text-inherit dark:bg-teal-500/30">
                {text.slice(idx, idx + q.length)}
            </mark>
            {text.slice(idx + q.length)}
        </>
    );
}

function flattenGroups(groups) {
    const flat = [];
    for (const group of groups) {
        for (const result of group.results ?? []) {
            flat.push({ ...result, category: group.category });
        }
    }
    return flat;
}

export default function CommandPalette({ open, onClose }) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(false);
    const [groups, setGroups] = useState([]);
    const [recents, setRecents] = useState(() => loadRecents());
    const [activeIndex, setActiveIndex] = useState(0);
    const inputRef = useRef(null);
    const abortRef = useRef(null);
    const rawNavigation = usePage().props.navigation ?? [];

    const pageItems = useMemo(() => {
        const sections = withNavIcons(rawNavigation);
        return sections.flatMap((section) =>
            (section.items ?? []).map((item) => ({
                id: `page-${item.id ?? item.href}`,
                title: t(`nav.${item.labelKey}`),
                subtitle: t(`nav.${section.labelKey}`),
                href: route(item.href),
                icon: item.iconKey ?? 'layout-dashboard',
                category: 'pages',
            })),
        );
    }, [rawNavigation, t]);

    const idlePages = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (q) {
            return [];
        }
        return pageItems.slice(0, 8);
    }, [pageItems, query]);

    const flatResults = useMemo(() => {
        if (!query.trim()) {
            const recentFlat = recents.map((r) => ({ ...r, category: 'recent' }));
            return [...recentFlat, ...idlePages];
        }
        return flattenGroups(groups);
    }, [query, groups, recents, idlePages]);

    useEffect(() => {
        if (open) {
            setQuery('');
            setGroups([]);
            setLoading(false);
            setActiveIndex(0);
            setRecents(loadRecents());
            requestAnimationFrame(() => inputRef.current?.focus());
        } else if (abortRef.current) {
            abortRef.current.abort();
        }
    }, [open]);

    useEffect(() => {
        const q = query.trim();
        if (!open) {
            return undefined;
        }

        if (q.length < 1) {
            setGroups([]);
            setLoading(false);
            return undefined;
        }

        setLoading(true);
        const controller = new AbortController();
        abortRef.current = controller;

        const timer = setTimeout(async () => {
            try {
                const data = await globalSearch(q, { signal: controller.signal });
                if (!controller.signal.aborted) {
                    setGroups(data.groups ?? []);
                    setActiveIndex(0);
                }
            } catch (err) {
                if (err?.name !== 'CanceledError' && err?.code !== 'ERR_CANCELED') {
                    setGroups([]);
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query, open]);

    useEffect(() => {
        setActiveIndex(0);
    }, [flatResults.length]);

    const navigate = (item) => {
        if (!item?.href) {
            return;
        }
        saveRecent({
            id: item.id,
            title: item.title,
            subtitle: item.subtitle,
            href: item.href,
            icon: item.icon,
        });
        onClose();
        router.visit(item.href);
    };

    const onKeyDown = (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveIndex((i) => Math.min(i + 1, Math.max(flatResults.length - 1, 0)));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((i) => Math.max(i - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const item = flatResults[activeIndex];
            if (item) {
                navigate(item);
            }
        }
    };

    if (!open) {
        return null;
    }

    const showIdle = !query.trim();
    const showEmpty = !loading && query.trim() && flatResults.length === 0;

    let runningIndex = -1;

    return (
        <div className="fixed inset-0 z-[100] flex items-start justify-center px-4 pt-[12vh]">
            <button
                type="button"
                className="absolute inset-0 bg-ink-900/60 backdrop-blur-sm"
                onClick={onClose}
                aria-label={t('common.closeMenu')}
            />
            <div className="relative z-10 w-full max-w-xl overflow-hidden rounded-2xl border border-rp-border bg-rp-surface shadow-2xl">
                <div className="flex items-center gap-3 border-b border-rp-border px-4 py-3">
                    <Search className="h-4 w-4 shrink-0 text-rp-text-muted" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder={t('common.commandPalette')}
                        className="w-full border-0 bg-transparent text-sm text-rp-text outline-none placeholder:text-rp-text-muted"
                        autoComplete="off"
                    />
                    {loading ? (
                        <Loader2 className="h-4 w-4 shrink-0 animate-spin text-rp-text-muted" />
                    ) : (
                        <kbd className="hidden rounded border border-rp-border bg-rp-surface-inset px-1.5 py-0.5 text-[10px] text-rp-text-muted sm:inline">
                            ESC
                        </kbd>
                    )}
                </div>

                <ScrollArea className="max-h-80 overflow-y-auto p-2">
                    {showEmpty ? (
                        <p className="px-3 py-8 text-center text-sm text-rp-text-muted">
                            {t('search.noResults')}
                        </p>
                    ) : null}

                    {showIdle && recents.length > 0 ? (
                        <div className="mb-2">
                            <p className="px-3 py-1.5 text-[10px] font-bold tracking-widest text-rp-text-muted uppercase">
                                {t('search.recent')}
                            </p>
                            <ul>
                                {recents.map((item) => {
                                    runningIndex += 1;
                                    const idx = runningIndex;
                                    const Icon = resolveNavIcon(item.icon) ?? Clock;
                                    return (
                                        <li key={`recent-${item.href}`}>
                                            <ResultButton
                                                active={idx === activeIndex}
                                                icon={Icon}
                                                title={item.title}
                                                subtitle={item.subtitle}
                                                query=""
                                                onClick={() => navigate(item)}
                                                onMouseEnter={() => setActiveIndex(idx)}
                                            />
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    ) : null}

                    {showIdle && idlePages.length > 0 ? (
                        <div>
                            <p className="px-3 py-1.5 text-[10px] font-bold tracking-widest text-rp-text-muted uppercase">
                                {t('search.categories.pages')}
                            </p>
                            <ul>
                                {idlePages.map((item) => {
                                    runningIndex += 1;
                                    const idx = runningIndex;
                                    const Icon = resolveNavIcon(item.icon);
                                    return (
                                        <li key={item.id}>
                                            <ResultButton
                                                active={idx === activeIndex}
                                                icon={Icon}
                                                title={item.title}
                                                subtitle={item.subtitle}
                                                query=""
                                                onClick={() => navigate(item)}
                                                onMouseEnter={() => setActiveIndex(idx)}
                                            />
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    ) : null}

                    {!showIdle
                        ? groups.map((group) => (
                              <div key={group.category} className="mb-1">
                                  <p className="px-3 py-1.5 text-[10px] font-bold tracking-widest text-rp-text-muted uppercase">
                                      {t(`search.categories.${group.category_label_key}`, {
                                          defaultValue: group.category,
                                      })}
                                  </p>
                                  <ul>
                                      {(group.results ?? []).map((item) => {
                                          runningIndex += 1;
                                          const idx = runningIndex;
                                          const Icon = resolveNavIcon(item.icon);
                                          const title = displayTitle(item, t);
                                          const subtitle = displaySubtitle(item, t);
                                          return (
                                              <li key={item.id}>
                                                  <ResultButton
                                                      active={idx === activeIndex}
                                                      icon={Icon}
                                                      title={title}
                                                      subtitle={subtitle}
                                                      query={query}
                                                      onClick={() =>
                                                          navigate({ ...item, title, subtitle })
                                                      }
                                                      onMouseEnter={() => setActiveIndex(idx)}
                                                  />
                                              </li>
                                          );
                                      })}
                                  </ul>
                              </div>
                          ))
                        : null}

                    {loading && query.trim() && groups.length === 0 ? (
                        <p className="px-3 py-6 text-center text-sm text-rp-text-muted">
                            {t('search.loading')}
                        </p>
                    ) : null}
                </ScrollArea>
            </div>
        </div>
    );
}

function ResultButton({ active, icon: Icon, title, subtitle, query, onClick, onMouseEnter }) {
    return (
        <button
            type="button"
            onClick={onClick}
            onMouseEnter={onMouseEnter}
            className={cn(
                'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition',
                active ? 'bg-rp-surface-inset' : 'hover:bg-rp-surface-inset',
            )}
        >
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-100 text-teal-500 dark:bg-teal-500/20 dark:text-teal-300">
                <Icon className="h-4 w-4" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium text-rp-text">
                    {highlightMatch(title, query)}
                </span>
                {subtitle ? (
                    <span className="block truncate text-xs text-rp-text-muted">{subtitle}</span>
                ) : null}
            </span>
        </button>
    );
}
