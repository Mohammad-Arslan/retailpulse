import { DASHBOARD_WIDGET_COMPONENTS } from '@/Components/dashboard/registry';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, usePage } from '@inertiajs/react';
import { Calendar } from 'lucide-react';
import { useTranslation } from 'react-i18next';

function Dashboard({ widgets = [] }) {
    const { auth } = usePage().props;
    const { t } = useTranslation();
    const rawName = auth?.user?.name?.trim() ?? '';
    const firstName = rawName ? rawName.split(/\s+/)[0] : t('pages.dashboard.greetingFallback');

    const today = new Date().toLocaleDateString(undefined, {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });

    const hour = new Date().getHours();
    const greeting =
        hour < 12
            ? t('pages.dashboard.greetingMorning')
            : hour < 17
              ? t('pages.dashboard.greetingAfternoon')
              : t('pages.dashboard.greetingEvening');

    return (
        <>
            <Head title={t('pages.dashboard.title')} />

            <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="font-display text-[28px] font-normal text-rp-text">
                        {greeting}, {firstName}.
                    </h1>
                    <p className="rp-page-desc">{t('pages.dashboard.description')}</p>
                </div>
                <div className="rp-pill-surface flex items-center gap-1.5">
                    <Calendar className="h-3.5 w-3.5 text-rp-text-muted" />
                    {today}
                </div>
            </div>

            {widgets.length === 0 ? (
                <div className="rp-card text-sm text-rp-text-muted">
                    {t('pages.dashboard.empty')}
                </div>
            ) : (
                widgets.map((widget) => {
                    const Component = DASHBOARD_WIDGET_COMPONENTS[widget.id];
                    if (!Component) {
                        return null;
                    }

                    return <Component key={widget.id} data={widget.data} />;
                })
            )}
        </>
    );
}

export default withAdminLayout(Dashboard);
