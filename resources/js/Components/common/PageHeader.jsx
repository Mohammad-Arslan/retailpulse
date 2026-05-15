export default function PageHeader({ title, description, children }) {
    return (
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 className="rp-page-title">{title}</h1>
                {description && (
                    <p className="rp-page-desc">{description}</p>
                )}
            </div>
            {children && (
                <div className="flex shrink-0 flex-wrap items-center gap-2.5">
                    {children}
                </div>
            )}
        </div>
    );
}
