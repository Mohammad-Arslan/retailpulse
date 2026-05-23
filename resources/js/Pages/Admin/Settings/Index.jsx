import PageHeader from '@/Components/common/PageHeader';
import AdminLayout from '@/Layouts/AdminLayout';
import { cn } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';
import {
    Bell,
    Building2,
    ChevronRight,
    Database,
    Settings,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

const ICONS = {
    settings: Settings,
    building: Building2,
    bell: Bell,
    database: Database,
};

export default function Index({ groups }) {
    const { t } = useTranslation();

    return (
        <AdminLayout>
            <Head title={t('pages.settings.title')} />

            <PageHeader
                title={t('pages.settings.title')}
                description={t('pages.settings.description')}
            />

            {groups.length === 0 ? (
                <p className="text-sm text-ink-500">{t('pages.settings.empty')}</p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {groups.map((group) => {
                        const Icon = ICONS[group.icon] ?? Settings;

                        return (
                            <Link
                                key={group.key}
                                href={route('admin.settings.edit', group.key)}
                                className={cn(
                                    'rp-card group flex flex-col gap-3 p-5 transition hover:border-teal-400/40 hover:shadow-md',
                                )}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400">
                                        <Icon className="h-5 w-5" />
                                    </span>
                                    <ChevronRight className="h-5 w-5 text-ink-400 transition group-hover:translate-x-0.5 group-hover:text-teal-500" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-ink-900 dark:text-white">
                                        {group.label}
                                    </h3>
                                    <p className="mt-1 text-sm text-ink-500 dark:text-ink-300">
                                        {group.description}
                                    </p>
                                </div>
                                {!group.can_update && (
                                    <span className="text-xs font-medium text-amber-600 dark:text-amber-400">
                                        {t('pages.settings.readOnly')}
                                    </span>
                                )}
                            </Link>
                        );
                    })}
                </div>
            )}
        </AdminLayout>
    );
}
