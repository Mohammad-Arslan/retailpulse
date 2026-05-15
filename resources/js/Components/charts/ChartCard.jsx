export default function ChartCard({ title, subtitle, children, className = '' }) {
    return (
        <div className={`rp-card flex flex-col ${className}`}>
            <div className="mb-4 shrink-0">
                <h2 className="text-[15px] font-semibold text-ink-900">{title}</h2>
                {subtitle && (
                    <p className="mt-0.5 text-xs text-ink-300">{subtitle}</p>
                )}
            </div>
            <div className="min-h-[220px] flex-1">{children}</div>
        </div>
    );
}
