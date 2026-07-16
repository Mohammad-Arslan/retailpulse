import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/Components/ui/alert-dialog';
import { Button } from '@/Components/ui/button';
import { useCan } from '@/Hooks/useCan';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function EmployeeTerminationActions({ employee }) {
    const { t } = useTranslation();
    const can = useCan();
    const [terminationDate, setTerminationDate] = useState(new Date().toISOString().slice(0, 10));
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    if (!can('hr.manage-employees')) {
        return null;
    }

    if (employee.status === 'terminated') {
        const reactivate = () => {
            setProcessing(true);
            router.post(
                route('admin.hr.employees.reactivate', employee.id),
                {},
                { preserveScroll: true, onFinish: () => setProcessing(false) },
            );
        };

        return (
            <AlertDialog>
                <AlertDialogTrigger asChild>
                    <Button type="button" variant="outline" disabled={processing}>
                        {t('pages.hrEmployees.reactivate')}
                    </Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('pages.hrEmployees.reactivateConfirmTitle')}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {t('pages.hrEmployees.reactivateConfirmDescription', { name: employee.name })}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{t('confirm.cancel')}</AlertDialogCancel>
                        <AlertDialogAction onClick={reactivate}>
                            {t('pages.hrEmployees.reactivate')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        );
    }

    const terminate = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.post(
            route('admin.hr.employees.terminate', employee.id),
            { termination_date: terminationDate },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogTrigger asChild>
                <Button type="button" variant="destructive">
                    {t('pages.hrEmployees.terminate')}
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <form onSubmit={terminate}>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('pages.hrEmployees.terminateConfirmTitle')}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {t('pages.hrEmployees.terminateConfirmDescription', { name: employee.name })}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="py-2">
                        <label className="mb-1.5 block text-sm font-medium text-rp-text" htmlFor="termination_date">
                            {t('pages.hrEmployees.fields.terminationDate')}
                        </label>
                        <input
                            id="termination_date"
                            type="date"
                            required
                            min={employee.hire_date ?? undefined}
                            className="rp-form-input"
                            value={terminationDate}
                            onChange={(e) => setTerminationDate(e.target.value)}
                        />
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel type="button">{t('confirm.cancel')}</AlertDialogCancel>
                        <AlertDialogAction type="submit" disabled={processing}>
                            {t('pages.hrEmployees.terminate')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </form>
            </AlertDialogContent>
        </AlertDialog>
    );
}
