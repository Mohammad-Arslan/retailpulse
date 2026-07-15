import AdminFormField from '@/Components/common/AdminFormField';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { cn, randomId } from '@/lib/utils';
import { ImagePlus, Plus, Trash2, X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';

const ACCEPT = 'image/jpeg,image/png,image/webp';

export function emptyImageBatch(type = 'photo') {
    return {
        id: randomId(),
        type,
        pending_images: [],
        cnic_front: null,
        cnic_back: null,
    };
}

function SlotCard({ label, previewUrl, name, onClear, onPick, disabled }) {
    const { t } = useTranslation();
    const inputRef = useRef(null);

    return (
        <div className="space-y-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-rp-text-muted">{label}</p>
            <div
                className={cn(
                    'relative overflow-hidden rounded-xl border border-dashed border-rp-border bg-rp-surface-inset',
                    previewUrl && 'border-solid',
                )}
            >
                {previewUrl ? (
                    <>
                        <img src={previewUrl} alt={name || label} className="aspect-[4/3] w-full object-cover" />
                        {!disabled && (
                            <button
                                type="button"
                                className="absolute right-2 top-2 rounded-full bg-black/60 p-1.5 text-white"
                                onClick={onClear}
                                aria-label={t('pages.hrEmployees.attachments.removeImage')}
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                        {name && (
                            <div className="absolute inset-x-0 bottom-0 truncate bg-black/55 px-2 py-1 text-[10px] text-white">
                                {name}
                            </div>
                        )}
                    </>
                ) : (
                    <button
                        type="button"
                        disabled={disabled}
                        onClick={() => inputRef.current?.click()}
                        className="flex aspect-[4/3] w-full flex-col items-center justify-center gap-2 text-sm text-rp-text-muted hover:text-rp-text disabled:opacity-50"
                    >
                        <ImagePlus className="h-6 w-6" />
                        {t('pages.hrEmployees.attachments.chooseImage')}
                    </button>
                )}
            </div>
            <input
                ref={inputRef}
                type="file"
                accept={ACCEPT}
                className="hidden"
                disabled={disabled}
                onChange={onPick}
            />
        </div>
    );
}

function BatchCard({
    batch,
    attachmentTypes,
    disabled,
    canRemove,
    remainingSlots,
    onChange,
    onRemove,
}) {
    const { t } = useTranslation();
    const multiRef = useRef(null);
    const isCnic = batch.type === 'cnic';

    const typeOptions = attachmentTypes.map((type) => ({
        value: type,
        label: t(`pages.hrEmployees.attachmentTypes.${type}`),
    }));

    const revokeFile = (entry) => {
        if (entry?.previewUrl) {
            URL.revokeObjectURL(entry.previewUrl);
        }
    };

    const update = (patch) => onChange({ ...batch, ...patch });

    const setType = (type) => {
        (batch.pending_images ?? []).forEach(revokeFile);
        revokeFile(batch.cnic_front);
        revokeFile(batch.cnic_back);
        update({
            type: type || 'photo',
            pending_images: [],
            cnic_front: null,
            cnic_back: null,
        });
    };

    const addFiles = (files) => {
        const next = files.slice(0, remainingSlots).map((file) => ({
            id: randomId(),
            file,
            previewUrl: URL.createObjectURL(file),
            name: file.name,
        }));
        update({ pending_images: [...(batch.pending_images ?? []), ...next] });
    };

    const removePending = (id) => {
        const item = (batch.pending_images ?? []).find((entry) => entry.id === id);
        revokeFile(item);
        update({
            pending_images: (batch.pending_images ?? []).filter((entry) => entry.id !== id),
        });
    };

    const setCnic = (side, file) => {
        const key = side === 'front' ? 'cnic_front' : 'cnic_back';
        revokeFile(batch[key]);
        update({
            [key]: file
                ? { file, previewUrl: URL.createObjectURL(file), name: file.name }
                : null,
        });
    };

    return (
        <div className="space-y-4 rounded-xl border border-rp-border p-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <AdminFormField
                    label={t('pages.hrEmployees.fields.attachmentType')}
                    className="min-w-[12rem] flex-1"
                >
                    <Select
                        value={batch.type}
                        isDisabled={disabled}
                        options={typeOptions}
                        onChange={setType}
                    />
                </AdminFormField>
                {canRemove && !disabled && (
                    <Button type="button" variant="ghost" onClick={onRemove}>
                        <Trash2 className="h-4 w-4" />
                        {t('pages.hrEmployees.attachments.removeBatch')}
                    </Button>
                )}
            </div>

            {isCnic ? (
                <div className="space-y-3">
                    <p className="text-sm text-rp-text-muted">{t('pages.hrEmployees.attachments.cnicHint')}</p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SlotCard
                            label={t('pages.hrEmployees.attachments.cnicFront')}
                            previewUrl={batch.cnic_front?.previewUrl}
                            name={batch.cnic_front?.name}
                            disabled={disabled}
                            onClear={() => setCnic('front', null)}
                            onPick={(event) => {
                                setCnic('front', event.target.files?.[0] ?? null);
                                event.target.value = '';
                            }}
                        />
                        <SlotCard
                            label={t('pages.hrEmployees.attachments.cnicBack')}
                            previewUrl={batch.cnic_back?.previewUrl}
                            name={batch.cnic_back?.name}
                            disabled={disabled}
                            onClear={() => setCnic('back', null)}
                            onPick={(event) => {
                                setCnic('back', event.target.files?.[0] ?? null);
                                event.target.value = '';
                            }}
                        />
                    </div>
                </div>
            ) : (
                <div className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm text-rp-text-muted">
                            {t('pages.hrEmployees.attachments.batchMultiHint')}
                        </p>
                        {!disabled && remainingSlots > 0 && (
                            <button
                                type="button"
                                className="rp-btn-outline"
                                onClick={() => multiRef.current?.click()}
                            >
                                <ImagePlus className="h-4 w-4" />
                                {t('pages.hrEmployees.attachments.addImages')}
                            </button>
                        )}
                    </div>
                    <input
                        ref={multiRef}
                        type="file"
                        accept={ACCEPT}
                        multiple
                        className="hidden"
                        disabled={disabled}
                        onChange={(event) => {
                            addFiles(Array.from(event.target.files ?? []));
                            event.target.value = '';
                        }}
                    />
                    {(batch.pending_images ?? []).length === 0 ? (
                        <p className="rounded-lg border border-dashed border-rp-border p-3 text-sm text-rp-text-muted">
                            {t('pages.hrEmployees.attachments.noPendingInBatch')}
                        </p>
                    ) : (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                            {(batch.pending_images ?? []).map((item) => (
                                <div
                                    key={item.id}
                                    className="group relative overflow-hidden rounded-xl border border-dashed border-teal-400/60 bg-rp-surface-inset"
                                >
                                    <img
                                        src={item.previewUrl}
                                        alt={item.name}
                                        className="aspect-square w-full object-cover"
                                    />
                                    <span className="absolute left-2 top-2 rounded-full bg-amber-500/90 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                                        {t('pages.hrEmployees.attachments.pendingUpload')}
                                    </span>
                                    {!disabled && (
                                        <button
                                            type="button"
                                            className="absolute right-2 top-2 rounded-full bg-black/60 p-1.5 text-white opacity-0 transition group-hover:opacity-100"
                                            onClick={() => removePending(item.id)}
                                            aria-label={t('pages.hrEmployees.attachments.removeImage')}
                                        >
                                            <X className="h-3.5 w-3.5" />
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default function EmployeeImageAttachments({
    batches = [],
    onBatchesChange,
    attachmentTypes = [],
    existingImages = [],
    removeImageIds = [],
    onRemoveImageIdsChange,
    maxImages = 10,
    disabled = false,
    errors = {},
}) {
    const { t } = useTranslation();

    const visibleExisting = existingImages.filter((image) => !removeImageIds.includes(image.id));
    const pendingCount = batches.reduce((sum, batch) => {
        if (batch.type === 'cnic') {
            return sum + (batch.cnic_front ? 1 : 0) + (batch.cnic_back ? 1 : 0);
        }
        return sum + (batch.pending_images?.length ?? 0);
    }, 0);
    const remainingSlots = Math.max(0, maxImages - visibleExisting.length - pendingCount);

    useEffect(
        () => () => {
            batches.forEach((batch) => {
                (batch.pending_images ?? []).forEach((item) => {
                    if (item.previewUrl) {
                        URL.revokeObjectURL(item.previewUrl);
                    }
                });
                if (batch.cnic_front?.previewUrl) {
                    URL.revokeObjectURL(batch.cnic_front.previewUrl);
                }
                if (batch.cnic_back?.previewUrl) {
                    URL.revokeObjectURL(batch.cnic_back.previewUrl);
                }
            });
        },
        // cleanup on unmount only
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    const updateBatch = (id, next) => {
        onBatchesChange(batches.map((batch) => (batch.id === id ? next : batch)));
    };

    const removeBatch = (id) => {
        const batch = batches.find((entry) => entry.id === id);
        if (batch) {
            (batch.pending_images ?? []).forEach((item) => {
                if (item.previewUrl) {
                    URL.revokeObjectURL(item.previewUrl);
                }
            });
            if (batch.cnic_front?.previewUrl) {
                URL.revokeObjectURL(batch.cnic_front.previewUrl);
            }
            if (batch.cnic_back?.previewUrl) {
                URL.revokeObjectURL(batch.cnic_back.previewUrl);
            }
        }
        onBatchesChange(batches.filter((entry) => entry.id !== id));
    };

    return (
        <div className="space-y-5">
            <div className="space-y-3">
                <p className="text-sm font-medium text-rp-text">
                    {t('pages.hrEmployees.attachments.savedImages')}
                </p>
                {visibleExisting.length === 0 ? (
                    <p className="rounded-lg border border-rp-border p-3 text-sm text-rp-text-muted">
                        {t('pages.hrEmployees.emptyAttachments')}
                    </p>
                ) : (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                        {visibleExisting.map((image) => (
                            <div
                                key={image.id}
                                className="group relative overflow-hidden rounded-xl border border-rp-border bg-rp-surface-inset"
                            >
                                <img
                                    src={image.thumbnail_url ?? image.url}
                                    alt={image.alt ?? image.original_filename ?? ''}
                                    className="aspect-square w-full object-cover"
                                />
                                {image.alt && (
                                    <span className="absolute left-2 top-2 rounded-full bg-teal-600/90 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                                        {image.alt === 'cnic_front'
                                            ? t('pages.hrEmployees.attachments.cnicFront')
                                            : image.alt === 'cnic_back'
                                              ? t('pages.hrEmployees.attachments.cnicBack')
                                              : t(`pages.hrEmployees.attachmentTypes.${image.alt}`, {
                                                    defaultValue: image.alt,
                                                })}
                                    </span>
                                )}
                                {!disabled && (
                                    <button
                                        type="button"
                                        className="absolute right-2 top-2 rounded-full bg-black/60 p-1.5 text-white opacity-0 transition group-hover:opacity-100"
                                        onClick={() =>
                                            onRemoveImageIdsChange([...removeImageIds, image.id])
                                        }
                                        aria-label={t('pages.hrEmployees.attachments.removeImage')}
                                    >
                                        <X className="h-3.5 w-3.5" />
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                )}
                {removeImageIds.length > 0 && (
                    <p className="text-xs text-amber-700 dark:text-amber-200">
                        {t('pages.hrEmployees.attachments.removedNotice', {
                            count: removeImageIds.length,
                        })}
                    </p>
                )}
            </div>

            {!disabled && (
                <div className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p className="text-sm font-medium text-rp-text">
                                {t('pages.hrEmployees.attachments.newUploads')}
                            </p>
                            <p className="text-xs text-rp-text-muted">
                                {t('pages.hrEmployees.attachments.addMoreHint', { max: maxImages })}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={remainingSlots <= 0}
                            onClick={() => onBatchesChange([...batches, emptyImageBatch('photo')])}
                        >
                            <Plus className="h-4 w-4" />
                            {t('pages.hrEmployees.attachments.addMore')}
                        </Button>
                    </div>

                    {batches.length === 0 && (
                        <p className="rounded-lg border border-dashed border-rp-border p-3 text-sm text-rp-text-muted">
                            {t('pages.hrEmployees.attachments.addMoreEmpty')}
                        </p>
                    )}

                    {batches.map((batch) => (
                        <BatchCard
                            key={batch.id}
                            batch={batch}
                            attachmentTypes={attachmentTypes}
                            disabled={disabled}
                            canRemove={batches.length > 0}
                            remainingSlots={remainingSlots}
                            onChange={(next) => updateBatch(batch.id, next)}
                            onRemove={() => removeBatch(batch.id)}
                        />
                    ))}
                </div>
            )}

            {(errors.image_uploads || errors.images) && (
                <p className="text-sm text-red-600">{errors.image_uploads || errors.images}</p>
            )}
        </div>
    );
}
