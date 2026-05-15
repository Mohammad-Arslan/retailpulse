import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import { useBreadcrumbs } from '@/Hooks/useBreadcrumbs';
import { Link } from '@inertiajs/react';
import { Fragment } from 'react';

export default function Breadcrumbs() {
    const items = useBreadcrumbs();

    if (items.length <= 1) {
        return null;
    }

    return (
        <Breadcrumb className="mb-5">
            <BreadcrumbList>
                {items.map((item, index) => {
                    const isLast = index === items.length - 1;

                    return (
                        <Fragment key={`${item.label}-${index}`}>
                            {index > 0 && (
                                <BreadcrumbSeparator className="text-rp-text-muted" />
                            )}
                            <BreadcrumbItem>
                                {isLast || !item.href ? (
                                    <BreadcrumbPage className="text-rp-text-secondary">
                                        {item.label}
                                    </BreadcrumbPage>
                                ) : (
                                    <BreadcrumbLink asChild>
                                        <Link
                                            href={item.href}
                                            className="text-rp-text-muted transition hover:text-teal-500"
                                        >
                                            {item.label}
                                        </Link>
                                    </BreadcrumbLink>
                                )}
                            </BreadcrumbItem>
                        </Fragment>
                    );
                })}
            </BreadcrumbList>
        </Breadcrumb>
    );
}
