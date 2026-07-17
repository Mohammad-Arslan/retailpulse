import {
    AlertDialog,
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

/**
 * AlertDialogAction renders Radix's DialogPrimitive.Close under the hood, which closes the
 * dialog synchronously on click regardless of request outcome. Using a plain Button here
 * instead keeps the dialog open until the request actually resolves, so a rejected
 * termination/reactivation isn't silently swallowed by an already-unmounted dialog.
 */
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
                    <Button type="button" variant="outline">
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
                        <AlertDialogCancel type="button" disabled={processing}>
                            {t('confirm.cancel')}
                        </AlertDialogCancel>
                        <Button type="button" onClick={reactivate} disabled={processing}>
                            {t('pages.hrEmployees.reactivate')}
                        </Button>
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
                        <AlertDialogCancel type="button" disabled={processing}>
                            {t('confirm.cancel')}
                        </AlertDialogCancel>
                        <Button type="submit" variant="destructive" disabled={processing}>
                            {t('pages.hrEmployees.terminate')}
                        </Button>
                    </AlertDialogFooter>
                </form>
            </AlertDialogContent>
        </AlertDialog>
    );
}
