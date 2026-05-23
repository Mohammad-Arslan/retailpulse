export default function PageHeader({ title, description, children }) {
    return (
        <div className="mb-6 grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
            <div className="min-w-0 max-w-2xl">
                <h1 className="rp-page-title">{title}</h1>
                {description && (
                    <p className="rp-page-desc">{description}</p>
                )}
            </div>
            {children && (
                <div className="flex w-full min-w-0 xl:min-w-[28rem] xl:max-w-2xl xl:justify-end">
                    {children}
                </div>
            )}
        </div>
    );
}
