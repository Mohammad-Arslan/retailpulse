import { Button } from '@/Components/ui/button';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuTrigger,
} from '@/Components/ui/context-menu';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { useCan } from '@/Hooks/useCan';
import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/react';
import {
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Eye,
    MoreHorizontal,
    Pencil,
    Trash2,
} from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const ACTION_ICONS = {
    view: Eye,
    edit: Pencil,
    delete: Trash2,
};

function SortIcon({ direction }) {
    if (direction === 'asc') {
        return <ArrowUp className="h-3.5 w-3.5" aria-hidden />;
    }

    if (direction === 'desc') {
        return <ArrowDown className="h-3.5 w-3.5" aria-hidden />;
    }

    return <ArrowUpDown className="h-3.5 w-3.5 opacity-40" aria-hidden />;
}

function RowActionItems({ actions, onNavigate, MenuItem = DropdownMenuItem }) {
    const can = useCan();
    const confirm = useConfirm();
    const { t } = useTranslation();

    const visible = actions.filter(
        (action) => !action.permission || can(action.permission),
    );

    if (visible.length === 0) {
        return null;
    }

    return visible.map((action) => {
        const Icon = action.icon ?? ACTION_ICONS[action.type] ?? Pencil;
        const isDestructive = action.variant === 'destructive';

        const handleSelect = async () => {
            if (action.confirm) {
                const confirmed = await confirm(
                    typeof action.confirm === 'string'
                        ? {
                              title: t('confirm.deleteTitle'),
                              description: action.confirm,
                              confirmLabel: t('common.delete'),
                              cancelLabel: t('confirm.cancel'),
                              variant: 'destructive',
                          }
                        : {
                              variant: 'destructive',
                              confirmLabel: t('common.delete'),
                              cancelLabel: t('confirm.cancel'),
                              ...action.confirm,
                          },
                );

                if (!confirmed) {
                    return;
                }
            }

            if (action.method === 'delete' && action.href) {
                router.delete(action.href, { preserveScroll: true });
                onNavigate?.();
                return;
            }

            if (action.onClick) {
                action.onClick();
                onNavigate?.();
            }
        };

        const itemClass = cn(
            'flex cursor-pointer items-center gap-2',
            isDestructive && 'text-rose-500 focus:text-rose-500',
        );

        if (action.href && action.method !== 'delete') {
            return (
                <MenuItem key={action.label} asChild>
                    <Link href={action.href} className={itemClass} onClick={onNavigate}>
                        <Icon className="h-4 w-4" />
                        {action.label}
                    </Link>
                </MenuItem>
            );
        }

        return (
            <MenuItem
                key={action.label}
                className={itemClass}
                onSelect={(event) => {
                    event.preventDefault();
                    void handleSelect();
                }}
            >
                <Icon className="h-4 w-4" />
                {action.label}
            </MenuItem>
        );
    });
}

function RowActionsMenu({ actions }) {
    const { t } = useTranslation();
    const can = useCan();

    const visible = actions.filter(
        (action) => !action.permission || can(action.permission),
    );

    if (visible.length === 0) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    className="h-8 w-8 text-rp-text-secondary hover:text-rp-text"
                    aria-label={t('common.actions')}
                >
                    <MoreHorizontal className="h-4 w-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <RowActionItems actions={actions} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function DataTable({
    columns,
    data,
    pagination,
    filters = {},
    indexRoute,
    rowActions,
    emptyMessage,
    className,
}) {
    const { t } = useTranslation();
    const rows = data ?? [];
    const paginator = pagination ?? null;

    const sort = filters.sort ?? null;
    const direction = filters.direction ?? 'asc';

    const tableColumns = useMemo(() => {
        const defs = [...columns];

        if (rowActions) {
            defs.push({
                id: '_actions',
                header: () => (
                    <span className="sr-only">{t('common.actions')}</span>
                ),
                enableSorting: false,
                cell: ({ row }) => (
                    <div className="flex justify-end">
                        <RowActionsMenu actions={rowActions(row.original)} />
                    </div>
                ),
            });
        }

        return defs;
    }, [columns, rowActions, t]);

    const table = useReactTable({
        data: rows,
        columns: tableColumns,
        getCoreRowModel: getCoreRowModel(),
        manualSorting: true,
    });

    const handleSort = (columnId) => {
        if (!indexRoute) {
            return;
        }

        const nextDirection =
            sort === columnId && direction === 'asc' ? 'desc' : 'asc';

        router.get(
            route(indexRoute),
            { ...filters, sort: columnId, direction: nextDirection },
            { preserveState: true, preserveScroll: true },
        );
    };

    const emptyLabel = emptyMessage ?? t('common.noResults');

    return (
        <div className={cn('rp-user-table-wrap', className)}>
            <Table>
                <TableHeader>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRow
                            key={headerGroup.id}
                            className="border-b border-rp-border hover:bg-transparent"
                        >
                            {headerGroup.headers.map((header) => {
                                const columnDef = header.column.columnDef;
                                const canSort =
                                    columnDef.enableSorting !== false &&
                                    columnDef.id !== '_actions' &&
                                    indexRoute;

                                return (
                                    <TableHead
                                        key={header.id}
                                        className={cn(
                                            'rp-table-head rp-table-head-bg h-auto px-4 py-3',
                                            columnDef.meta?.headClassName,
                                        )}
                                    >
                                        {header.isPlaceholder ? null : canSort ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleSort(header.column.id)
                                                }
                                                className="inline-flex items-center gap-1.5 rounded-md text-[11px] font-bold tracking-wider text-ink-300 uppercase transition hover:text-teal-500 focus-visible:ring-2 focus-visible:ring-teal-400 focus-visible:outline-none"
                                            >
                                                {flexRender(
                                                    columnDef.header,
                                                    header.getContext(),
                                                )}
                                                <SortIcon
                                                    direction={
                                                        sort === header.column.id
                                                            ? direction
                                                            : null
                                                    }
                                                />
                                            </button>
                                        ) : (
                                            flexRender(
                                                columnDef.header,
                                                header.getContext(),
                                            )
                                        )}
                                    </TableHead>
                                );
                            })}
                        </TableRow>
                    ))}
                </TableHeader>
                <TableBody>
                    {table.getRowModel().rows.length === 0 ? (
                        <TableRow className="hover:bg-transparent">
                            <TableCell
                                colSpan={tableColumns.length}
                                className="px-4 py-12 text-center text-sm text-rp-text-muted"
                            >
                                {emptyLabel}
                            </TableCell>
                        </TableRow>
                    ) : (
                        table.getRowModel().rows.map((row) => {
                            const actions = rowActions
                                ? rowActions(row.original)
                                : [];

                            const rowInner = (
                                <TableRow
                                    key={row.id}
                                    className="group border-b border-sand-100 last:border-0 hover:bg-teal-500/[0.02]"
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell
                                            key={cell.id}
                                            className={cn(
                                                'px-4 py-3',
                                                cell.column.columnDef.meta
                                                    ?.cellClassName,
                                            )}
                                        >
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            );

                            if (!rowActions || actions.length === 0) {
                                return rowInner;
                            }

                            return (
                                <ContextMenu key={row.id}>
                                    <ContextMenuTrigger asChild>
                                        {rowInner}
                                    </ContextMenuTrigger>
                                    <ContextMenuContent className="w-44">
                                        <RowActionItems
                                            actions={actions}
                                            MenuItem={ContextMenuItem}
                                        />
                                    </ContextMenuContent>
                                </ContextMenu>
                            );
                        })
                    )}
                </TableBody>
            </Table>

            {paginator && paginator.last_page > 1 && (
                <div className="flex flex-col gap-3 border-t border-rp-border bg-rp-surface-inset px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                    <span className="text-[13px] text-rp-text-secondary">
                        {t('common.showing', {
                            from: paginator.from,
                            to: paginator.to,
                            total: paginator.total,
                        })}
                    </span>
                    <div className="flex flex-wrap gap-1.5">
                        {paginator.links?.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    preserveState
                                    className={cn(
                                        'flex h-8 min-w-8 items-center justify-center rounded-[7px] border px-2 text-[13px] font-medium transition focus-visible:ring-2 focus-visible:ring-teal-400 focus-visible:outline-none',
                                        link.active
                                            ? 'border-ink-900 bg-ink-900 text-white'
                                            : 'border-sand-200 bg-white text-ink-700 hover:border-ink-900 hover:bg-ink-900 hover:text-white',
                                    )}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : (
                                <span
                                    key={i}
                                    className="flex h-8 min-w-8 items-center justify-center rounded-[7px] border border-sand-200 bg-white px-2 text-[13px] text-ink-300"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
