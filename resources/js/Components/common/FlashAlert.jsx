export default function FlashAlert({ flash }) {
    if (!flash?.success && !flash?.error) {
        return null;
    }

    return (
        <div className="mb-6 space-y-3">
            {flash.success && (
                <div className="rounded-xl border border-teal-100 bg-teal-100/60 px-4 py-3 text-sm text-teal-500 dark:border-teal-500/30 dark:bg-teal-500/15 dark:text-teal-300">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="rounded-xl border border-rose-100 bg-rose-100/60 px-4 py-3 text-sm text-rose-500 dark:border-rose-500/30 dark:bg-rose-500/15 dark:text-rose-300">
                    {flash.error}
                </div>
            )}
        </div>
    );
}
