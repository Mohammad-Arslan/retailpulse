import { cn, randomId } from '@/lib/utils';
import { ImagePlus, Star, X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';

const ACCEPT = 'image/jpeg,image/png,image/webp';

function pendingImageId() {
    return randomId();
}

export default function ProductImageUploader({
    existingImages = [],
    removedImageIds = [],
    onRemovedChange,
    pendingImages = [],
    onPendingChange,
    errors,
    maxImages = 10,
    disabled = false,
}) {
    const { t } = useTranslation();
    const inputRef = useRef(null);

    const visibleExisting = existingImages.filter(
        (image) => !removedImageIds.includes(image.id),
    );
    const remainingSlots = Math.max(
        0,
        maxImages - visibleExisting.length - pendingImages.length,
    );
    const canAddMore = remainingSlots > 0 && !disabled;

    useEffect(
        () => () => {
            pendingImages.forEach((item) => {
                if (item.previewUrl) {
                    URL.revokeObjectURL(item.previewUrl);
                }
            });
        },
        [pendingImages],
    );

    const handleSelect = (event) => {
        const files = Array.from(event.target.files ?? []);
        if (files.length === 0) {
            return;
        }

        const next = files.slice(0, remainingSlots).map((file) => ({
            id: pendingImageId(),
            file,
            previewUrl: URL.createObjectURL(file),
            name: file.name,
        }));

        onPendingChange([...pendingImages, ...next]);
        event.target.value = '';
    };

    const removePending = (id) => {
        const item = pendingImages.find((entry) => entry.id === id);
        if (item?.previewUrl) {
            URL.revokeObjectURL(item.previewUrl);
        }
        onPendingChange(pendingImages.filter((entry) => entry.id !== id));
    };

    const markExistingRemoved = (id) => {
        onRemovedChange([...removedImageIds, id]);
    };

    const restoreExisting = (id) => {
        onRemovedChange(removedImageIds.filter((entry) => entry !== id));
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-sm font-medium text-rp-text">
                        {t('pages.products.sections.images')}
                    </p>
                    <p className="mt-1 text-xs text-rp-text-muted">
                        {t('pages.products.imagesHint', { max: maxImages })}
                    </p>
                </div>
                {canAddMore && (
                    <button
                        type="button"
                        className="rp-btn-outline"
                        onClick={() => inputRef.current?.click()}
                    >
                        <ImagePlus className="h-4 w-4" />
                        {t('pages.products.addImages')}
                    </button>
                )}
            </div>

            <input
                ref={inputRef}
                type="file"
                accept={ACCEPT}
                multiple
                className="hidden"
                onChange={handleSelect}
            />

            {(visibleExisting.length > 0 || pendingImages.length > 0) && (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    {visibleExisting.map((image) => (
                        <div
                            key={`existing-${image.id}`}
                            className="group relative overflow-hidden rounded-xl border border-rp-border bg-rp-surface-inset"
                        >
                            <img
                                src={image.thumbnail_url ?? image.url}
                                alt={image.alt ?? image.original_filename ?? ''}
                                className="aspect-square w-full object-cover"
                            />
                            {image.is_primary && (
                                <span className="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-teal-600/90 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                                    <Star className="h-3 w-3 fill-current" />
                                    {t('pages.products.primaryImage')}
                                </span>
                            )}
                            {!disabled && (
                                <button
                                    type="button"
                                    aria-label={t('pages.products.removeImage')}
                                    className="absolute right-2 top-2 rounded-full bg-black/60 p-1.5 text-white opacity-0 transition group-hover:opacity-100"
                                    onClick={() => markExistingRemoved(image.id)}
                                >
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </div>
                    ))}

                    {pendingImages.map((item) => (
                        <div
                            key={`pending-${item.id}`}
                            className="group relative overflow-hidden rounded-xl border border-dashed border-teal-400/60 bg-rp-surface-inset"
                        >
                            <img
                                src={item.previewUrl}
                                alt={item.name}
                                className="aspect-square w-full object-cover"
                            />
                            <span className="absolute left-2 top-2 rounded-full bg-amber-500/90 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                                {t('pages.products.pendingUpload')}
                            </span>
                            {!disabled && (
                                <button
                                    type="button"
                                    aria-label={t('pages.products.removeImage')}
                                    className="absolute right-2 top-2 rounded-full bg-black/60 p-1.5 text-white opacity-0 transition group-hover:opacity-100"
                                    onClick={() => removePending(item.id)}
                                >
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            )}
                            <div className="absolute inset-x-0 bottom-0 truncate bg-black/55 px-2 py-1 text-[10px] text-white">
                                {item.name}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {removedImageIds.length > 0 && (
                <div className="rounded-lg border border-amber-400/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-800 dark:text-amber-200">
                    {t('pages.products.removedImagesNotice', { count: removedImageIds.length })}
                    <div className="mt-2 flex flex-wrap gap-2">
                        {removedImageIds.map((id) => {
                            const image = existingImages.find((entry) => entry.id === id);
                            if (!image) {
                                return null;
                            }

                            return (
                                <button
                                    key={id}
                                    type="button"
                                    className="rounded-md bg-white/70 px-2 py-1 text-xs font-medium text-amber-900 dark:bg-ink-900/60 dark:text-amber-100"
                                    onClick={() => restoreExisting(id)}
                                >
                                    {t('pages.products.undoRemove', {
                                        name: image.original_filename ?? `#${id}`,
                                    })}
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}

            {errors?.images && (
                <p className={cn('text-sm text-red-600')}>{errors.images}</p>
            )}
            {errors?.['remove_image_ids'] && (
                <p className={cn('text-sm text-red-600')}>{errors['remove_image_ids']}</p>
            )}
        </div>
    );
}
