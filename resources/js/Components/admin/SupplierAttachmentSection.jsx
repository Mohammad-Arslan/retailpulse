import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { Download, FileText, ImagePlus, Paperclip, Trash2, Upload, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

function isImageMime(mime) {
    return typeof mime === 'string' && mime.startsWith('image/');
}

function formatFileSize(bytes) {
    if (!bytes) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function SupplierAttachmentSection({ supplierId, attachments = [] }) {
    const { t } = useTranslation();
    const confirm = useConfirm();
    const fileInputRef = useRef(null);
    const [attachmentNotes, setAttachmentNotes] = useState('');
    const [selectedFile, setSelectedFile] = useState(null);
    const [previewUrl, setPreviewUrl] = useState(null);
    const [isDragging, setIsDragging] = useState(false);

    useEffect(
        () => () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        },
        [previewUrl],
    );

    const revokePreview = () => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            setPreviewUrl(null);
        }
    };

    const clearSelectedFile = () => {
        revokePreview();
        setSelectedFile(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const selectFile = (file) => {
        if (!file) {
            return;
        }

        revokePreview();
        setSelectedFile(file);

        if (isImageMime(file.type)) {
            setPreviewUrl(URL.createObjectURL(file));
        }
    };

    const uploadAttachment = (e) => {
        e.preventDefault();
        if (!selectedFile) {
            return;
        }

        router.post(
            route('admin.suppliers.attachments.store', supplierId),
            { file: selectedFile, notes: attachmentNotes || null },
            {
                forceFormData: true,
                onSuccess: () => {
                    setAttachmentNotes('');
                    clearSelectedFile();
                },
            },
        );
    };

    const deleteAttachment = async (attachment) => {
        const confirmed = await confirm({
            title: t('confirm.deleteTitle'),
            description: t('confirm.deleteDescription', { name: attachment.file_name }),
            confirmLabel: t('common.delete'),
            cancelLabel: t('confirm.cancel'),
            variant: 'destructive',
        });

        if (!confirmed) {
            return;
        }

        router.delete(route('admin.suppliers.attachments.destroy', [supplierId, attachment.id]));
    };

    return (
        <div className="mb-6 rounded-lg border bg-card p-6">
            <h3 className="mb-3 font-medium">{t('pages.suppliers.attachments.title')}</h3>

            <form onSubmit={uploadAttachment} className="mb-4 space-y-4 border-b pb-4">
                <div>
                    <label className="text-xs text-rp-text-muted">{t('pages.suppliers.attachments.file')}</label>
                    <input
                        ref={fileInputRef}
                        type="file"
                        className="hidden"
                        onChange={(e) => selectFile(e.target.files?.[0] ?? null)}
                    />
                    <div
                        role="button"
                        tabIndex={0}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                                e.preventDefault();
                                fileInputRef.current?.click();
                            }
                        }}
                        onClick={() => fileInputRef.current?.click()}
                        onDragOver={(e) => {
                            e.preventDefault();
                            setIsDragging(true);
                        }}
                        onDragLeave={() => setIsDragging(false)}
                        onDrop={(e) => {
                            e.preventDefault();
                            setIsDragging(false);
                            selectFile(e.dataTransfer.files?.[0] ?? null);
                        }}
                        className={cn(
                            'mt-1 flex min-h-[8.5rem] cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-5 text-center transition',
                            isDragging
                                ? 'border-teal-400 bg-teal-500/10'
                                : 'border-rp-border bg-rp-surface-inset hover:border-teal-400/60',
                        )}
                    >
                        <Upload className="h-7 w-7 text-rp-text-muted" />
                        <p className="mt-2 text-sm font-medium text-rp-text">
                            {t('pages.suppliers.attachments.dropHint')}
                        </p>
                        <p className="mt-1 text-xs text-rp-text-muted">
                            {t('pages.suppliers.attachments.dropSubhint')}
                        </p>
                    </div>
                </div>

                {selectedFile && (
                    <div className="flex items-start gap-3 rounded-xl border border-rp-border bg-rp-surface-inset p-3">
                        {previewUrl ? (
                            <img
                                src={previewUrl}
                                alt={selectedFile.name}
                                className="h-16 w-16 shrink-0 rounded-lg object-cover"
                            />
                        ) : (
                            <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg bg-rp-surface text-rp-text-muted">
                                <FileText className="h-7 w-7" />
                            </div>
                        )}
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-rp-text">{selectedFile.name}</p>
                            <p className="text-xs text-rp-text-muted">{formatFileSize(selectedFile.size)}</p>
                        </div>
                        <button
                            type="button"
                            aria-label={t('pages.suppliers.attachments.removeSelected')}
                            className="rounded-full p-1.5 text-rp-text-muted transition hover:bg-rp-surface hover:text-rp-text"
                            onClick={clearSelectedFile}
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                )}

                <div className="flex flex-wrap items-end gap-3">
                    <div className="min-w-[200px] flex-1">
                        <label className="text-xs text-rp-text-muted">{t('pages.suppliers.attachments.notes')}</label>
                        <input
                            className="rp-form-input mt-1 w-full"
                            value={attachmentNotes}
                            onChange={(e) => setAttachmentNotes(e.target.value)}
                            placeholder={t('pages.suppliers.attachments.notesPlaceholder')}
                        />
                    </div>
                    <Button type="submit" disabled={!selectedFile}>
                        <Paperclip className="h-4 w-4" />
                        {t('pages.suppliers.attachments.upload')}
                    </Button>
                </div>
            </form>

            {attachments.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('pages.suppliers.attachments.empty')}</p>
            ) : (
                <ul className="grid gap-3 sm:grid-cols-2">
                    {attachments.map((att) => {
                        const image = isImageMime(att.mime_type);

                        return (
                            <li
                                key={att.id}
                                className="flex gap-3 rounded-xl border border-rp-border bg-rp-surface-inset p-3"
                            >
                                {image && att.preview_url ? (
                                    <a
                                        href={att.preview_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="shrink-0 overflow-hidden rounded-lg border border-rp-border"
                                    >
                                        <img
                                            src={att.preview_url}
                                            alt={att.file_name}
                                            className="h-20 w-20 object-cover"
                                        />
                                    </a>
                                ) : (
                                    <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded-lg border border-rp-border bg-rp-surface text-rp-text-muted">
                                        {image ? (
                                            <ImagePlus className="h-7 w-7" />
                                        ) : (
                                            <FileText className="h-7 w-7" />
                                        )}
                                    </div>
                                )}
                                <div className="flex min-w-0 flex-1 flex-col justify-between gap-2">
                                    <div className="min-w-0">
                                        <div className="truncate text-sm font-medium text-rp-text">
                                            {att.file_name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {formatFileSize(att.file_size)}
                                            {att.uploaded_by ? ` · ${att.uploaded_by}` : ''}
                                            {att.created_at ? ` · ${att.created_at.slice(0, 10)}` : ''}
                                        </div>
                                        {att.notes && (
                                            <p className="mt-1 line-clamp-2 text-xs text-rp-text-secondary">
                                                {att.notes}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex gap-2">
                                        <a
                                            href={route('admin.suppliers.attachments.download', [
                                                supplierId,
                                                att.id,
                                            ])}
                                            className="rp-btn-outline text-xs"
                                        >
                                            <Download className="h-3.5 w-3.5" />
                                            {t('common.download')}
                                        </a>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            className="text-rose-600 hover:text-rose-700"
                                            onClick={() => deleteAttachment(att)}
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
