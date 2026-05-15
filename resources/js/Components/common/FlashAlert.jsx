export default function FlashAlert({ flash }) {
    if (!flash?.success && !flash?.error) {
        return null;
    }

    return (
        <div className="mb-6 space-y-3">
            {flash.success && (
                <div className="rounded-xl border border-teal-100 bg-teal-100/60 px-4 py-3 text-sm text-teal-500">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="rounded-xl border border-rose-100 bg-rose-100/60 px-4 py-3 text-sm text-rose-500">
                    {flash.error}
                </div>
            )}
        </div>
    );
}
